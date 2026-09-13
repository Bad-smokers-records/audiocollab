<?php
namespace OCA\Audiocollab\Service;

use OCA\Audiocollab\AppInfo\Application;
use OCA\Audiocollab\Db\TrackVersionMapper;
use OCA\Audiocollab\Db\TrackVersion;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\Node;
use OCP\IUser;

/**
 * Genera e mantiene aggiornata la cache di streaming/waveform/loudness di
 * una traccia (chiamata dal container "audiotools"). Usata sia dal percorso
 * "lazy" (ApiController, alla prima apertura del player) sia dal job in
 * background che gira dopo un upload/sovrascrittura del file.
 */
class TrackCacheService {

    private $versionMapper;
    private $appConfig;
    private $ffmpegServiceUrl = 'http://localhost:3100';
    private $cacheBasePath;

    public function __construct(TrackVersionMapper $versionMapper, IAppConfig $appConfig) {
        $this->versionMapper = $versionMapper;
        $this->appConfig = $appConfig;
        $this->cacheBasePath = '/volume1/web/nextcloud-data/appdata_audiocollab';
    }

    /**
     * Assicura che esista una TrackVersion aggiornata al contenuto attuale
     * del file (basandosi sull'etag). Se il contenuto e' cambiato rispetto
     * all'ultima versione conosciuta, ne crea una nuova (riusando quella
     * esistente se l'etag corrisponde a un ripristino di uno stato precedente).
     */
    public function ensureVersionForCurrentContent(int $fileId, Node $file, IUser $user): TrackVersion {
        $realPath = $file->getStorage()->getLocalFile($file->getInternalPath());

        $this->syncNativeVersionHistory($fileId, $file, $user);

        $latest = $this->versionMapper->findLatestByFileId($fileId);
        $currentEtag = $file->getEtag();
        if ($latest === null || $latest->getEtag() !== $currentEtag) {
            // Il contenuto è cambiato: se corrisponde a una versione nostra già vista
            // (es. un ripristino da Nextcloud di uno stato precedente), la riusiamo
            // invece di duplicarla.
            $existing = $latest !== null ? $this->versionMapper->findByEtag($fileId, $currentEtag) : null;
            $latest = $existing ?? $this->createNewVersion($fileId, $realPath, $user->getUID(), $currentEtag, $file->getMTime(), $latest);
        }

        return $latest;
    }

    /**
     * Importa retroattivamente le versioni storiche native di Nextcloud
     * (precedenti al primo utilizzo di AudioCollab su questo file) come
     * versioni nostre, così la numerazione riflette l'intera cronologia
     * reale del file e non solo quanto osservato da quando lo guardiamo.
     */
    private function syncNativeVersionHistory(int $fileId, Node $file, IUser $user): void {
        try {
            $manager = \OC::$server->get(IVersionManager::class);
            $nativeVersions = $manager->getVersionsForFile($user, $file);
        } catch (\Throwable $e) {
            return;
        }

        $currentMTime = $file->getMTime();
        $historical = array_filter($nativeVersions, function ($nv) use ($currentMTime) {
            return (int)round($nv->getTimestamp()) !== $currentMTime;
        });
        if (empty($historical)) {
            return;
        }

        $existingRows = $this->versionMapper->findAllByFileId($fileId);
        $knownRevisionIds = [];
        foreach ($existingRows as $row) {
            if ($row->getNativeRevisionId() !== null) {
                $knownRevisionIds[] = $row->getNativeRevisionId();
            }
        }

        $missing = [];
        foreach ($historical as $nv) {
            $revId = (int)round($nv->getTimestamp());
            if (in_array($revId, $knownRevisionIds, true)) {
                continue;
            }
            // Evita duplicati se getVersionsForFile() restituisce più voci con lo stesso revisionId
            if (isset($missing[$revId])) {
                continue;
            }
            $missing[$revId] = $nv;
        }
        if (empty($missing)) {
            return;
        }

        if (!is_dir($this->cacheBasePath)) {
            mkdir($this->cacheBasePath, 0770, true);
        }

        foreach ($missing as $nv) {
            $revId = (int)round($nv->getTimestamp());
            try {
                $handle = $manager->read($nv);
            } catch (\Throwable $e) {
                continue;
            }
            if ($handle === false) {
                continue;
            }

            $tmpPath = $this->cacheBasePath . '/tmp_' . $fileId . '_' . $revId . '_' . basename($nv->getSourceFileName());
            $out = fopen($tmpPath, 'w');
            stream_copy_to_stream($handle, $out);
            fclose($out);
            fclose($handle);

            $mp3Path = $this->cacheBasePath . '/' . $fileId . '_rev' . $revId . '.mp3';
            $analysis = $this->analyzeAndCache($tmpPath, $mp3Path);
            unlink($tmpPath);

            $historicalVersion = new TrackVersion();
            $historicalVersion->setTrackId(0);
            $historicalVersion->setFileId($fileId);
            $historicalVersion->setMp3CachePath($mp3Path);
            $historicalVersion->setWaveformJson($analysis['waveformJson'] ?: '{"peaks":[]}');
            $historicalVersion->setVersionNumber(0);
            $nvUser = $nv->getUser();
            $historicalVersion->setUploadedBy($nvUser ? $nvUser->getUID() : $user->getUID());
            $historicalVersion->setUploadedAt(gmdate('Y-m-d H:i:s', $revId));
            $historicalVersion->setNativeRevisionId($revId);
            $historicalVersion->setIntegratedLoudness($analysis['integratedLoudness']);
            $historicalVersion->setLoudnessRange($analysis['loudnessRange']);
            $historicalVersion->setTruePeak($analysis['truePeak']);
            $historicalVersion->setExtractedArtist($analysis['artist']);
            $historicalVersion->setExtractedTitle($analysis['title']);
            $historicalVersion->setSourceCodec($analysis['sourceCodec']);
            $historicalVersion->setSourceBitrateKbps($analysis['sourceBitrateKbps']);
            $historicalVersion->setStatus('draft');
            $this->versionMapper->insert($historicalVersion);
        }

        $this->renumberVersions($fileId);
    }

    private function renumberVersions(int $fileId): void {
        $rows = $this->versionMapper->findAllByFileId($fileId);
        usort($rows, function (TrackVersion $a, TrackVersion $b) {
            $ta = $a->getNativeRevisionId() ?? strtotime($a->getUploadedAt());
            $tb = $b->getNativeRevisionId() ?? strtotime($b->getUploadedAt());
            return $ta <=> $tb;
        });
        $number = 1;
        foreach ($rows as $row) {
            if ($row->getVersionNumber() !== $number) {
                $row->setVersionNumber($number);
                $this->versionMapper->update($row);
            }
            $number++;
        }
    }

    private function createNewVersion(int $fileId, string $realPath, string $uid, string $etag, int $nativeRevisionId, ?TrackVersion $previous): TrackVersion {
        if (!is_dir($this->cacheBasePath)) {
            mkdir($this->cacheBasePath, 0770, true);
        }

        $versionNumber = $previous ? $previous->getVersionNumber() + 1 : 1;
        $mp3Path = $this->cacheBasePath . '/' . $fileId . '_v' . $versionNumber . '.mp3';

        $analysis = $this->analyzeAndCache($realPath, $mp3Path);

        $version = new TrackVersion();
        $version->setTrackId(0); // placeholder finché non implementiamo la gestione progetti
        $version->setFileId($fileId);
        $version->setMp3CachePath($mp3Path);
        $version->setWaveformJson($analysis['waveformJson'] ?: '{"peaks":[]}');
        $version->setVersionNumber($versionNumber);
        $version->setUploadedBy($uid);
        $version->setUploadedAt(gmdate('Y-m-d H:i:s', $nativeRevisionId));
        $version->setEtag($etag);
        $version->setNativeRevisionId($nativeRevisionId);
        $version->setStatus('draft');
        $version->setIntegratedLoudness($analysis['integratedLoudness']);
        $version->setLoudnessRange($analysis['loudnessRange']);
        $version->setTruePeak($analysis['truePeak']);
        $version->setExtractedArtist($analysis['artist']);
        $version->setExtractedTitle($analysis['title']);
        $version->setSourceCodec($analysis['sourceCodec']);
        $version->setSourceBitrateKbps($analysis['sourceBitrateKbps']);
        if ($previous) {
            $version->setArtistOverride($previous->getArtistOverride());
            $version->setTitleOverride($previous->getTitleOverride());
        }

        return $this->versionMapper->insert($version);
    }

    /**
     * Chiama l'endpoint unico /analyze-all del container audiotools (un solo
     * upload del file) e scrive il risultato in cache: copia diretta del file
     * originale se la transcodifica e' stata saltata (mp3 gia' ad alto
     * bitrate), altrimenti il file transcodificato ricevuto dal container.
     */
    private function analyzeAndCache(string $sourcePath, string $mp3Path): array {
        $result = $this->callAnalyzeAll($sourcePath);

        if (!empty($result['transcoded']) && !empty($result['mp3Base64'])) {
            file_put_contents($mp3Path, base64_decode($result['mp3Base64']));
        } else {
            copy($sourcePath, $mp3Path);
        }

        return [
            'waveformJson' => json_encode($result['waveform'] ?? ['peaks' => []]),
            'artist' => $result['metadata']['artist'] ?? null,
            'title' => $result['metadata']['title'] ?? null,
            'integratedLoudness' => $result['loudness']['integratedLoudness'] ?? null,
            'loudnessRange' => $result['loudness']['loudnessRange'] ?? null,
            'truePeak' => $result['loudness']['truePeak'] ?? null,
            'sourceCodec' => $result['sourceCodec'] ?? null,
            'sourceBitrateKbps' => $result['sourceBitrateKbps'] ?? null,
        ];
    }

    private function callAnalyzeAll(string $filePath): array {
        $loudnessEnabled = $this->appConfig->getAppValueBool(Application::CONFIG_LOUDNESS_MATCHING, true);
        $result = $this->callFfmpegService('/analyze-all', $filePath, null, 180, [
            'skip_loudness' => $loudnessEnabled ? '0' : '1',
        ]);
        if ($result === null) {
            return [];
        }
        $decoded = json_decode($result, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Fallback per le versioni create prima dell'introduzione della cache dei
     * metadati (extracted_artist/extracted_title): estrae artista/titolo al
     * momento, una tantum. Le versioni nuove non passano più di qui.
     */
    public function fetchLegacyMetadata(string $filePath): array {
        $result = $this->callFfmpegService('/metadata', $filePath, null);
        if ($result === null) {
            return [];
        }
        $decoded = json_decode($result, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function callFfmpegService(string $endpoint, string $filePath, ?string $saveTo, int $timeout = 120, array $extraFields = []): ?string {
        $curl = curl_init();
        $boundary = uniqid();
        $fileContent = file_get_contents($filePath);
        $body = '';
        foreach ($extraFields as $name => $value) {
            $body .= "--$boundary\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
            $body .= $value . "\r\n";
        }
        $body .= "--$boundary\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($filePath) . '"' . "\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--$boundary--\r\n";

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->ffmpegServiceUrl . $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data; boundary=' . $boundary],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            return null;
        }

        if ($saveTo !== null) {
            file_put_contents($saveTo, $response);
            return $saveTo;
        }

        return $response;
    }
}
