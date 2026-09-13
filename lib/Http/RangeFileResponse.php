<?php
namespace OCA\Audiocollab\Http;

use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;

class RangeFileResponse extends Response implements ICallbackResponse {
    private $path;
    private $start;
    private $length;

    public function __construct(string $path, int $start, int $length, int $status) {
        parent::__construct($status);
        $this->path = $path;
        $this->start = $start;
        $this->length = $length;
    }

    public function callback(IOutput $output): void {
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            return;
        }
        fseek($handle, $this->start);
        $stdout = fopen('php://output', 'wb');
        stream_copy_to_stream($handle, $stdout, $this->length);
        fclose($handle);
        fclose($stdout);
    }
}
