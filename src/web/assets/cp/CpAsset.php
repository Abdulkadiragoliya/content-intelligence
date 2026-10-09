<?php

namespace abdulkadiragoliya\contentintelligence\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

/**
 * Control Panel Asset Bundle for Content Intelligence.
 */
class CpAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CraftCpAsset::class,
        ];

        $this->css = [
            'css/cp.css',
        ];

        $this->js = [
            'js/cp.js',
        ];

        parent::init();
    }
}
