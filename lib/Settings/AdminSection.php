<?php
namespace OCA\Audiocollab\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {

    public function __construct(
        private IURLGenerator $url,
    ) {
    }

    public function getIcon(): string {
        return $this->url->imagePath('audiocollab', 'app.svg');
    }

    public function getID(): string {
        return 'audiocollab';
    }

    public function getName(): string {
        return 'AudioCollab';
    }

    public function getPriority(): int {
        return 75;
    }
}
