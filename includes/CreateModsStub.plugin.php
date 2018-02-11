<?php

namespace islandora_rest_ingester\plugins;

/**
 * Class for Islandora REST Ingester Example plugin.
 *
 * Creates a very basic MODS file for an object if none exists.
 */
class CreateModsStub extends Plugin
{
    /**
     * @param string $dir
     *    The current object input directory.
     * @param object $log
     *    The Monolog logger.
     * @param object $command
     *    The Commando command used in ingest.php.
     */
    public function __construct($dir, $log, $command)
    {
        parent::__construct($dir, $log, $command);
    }

    public function execute()
    {
        $title = basename($this->dir);

        // @codingStandardsIgnoreStart
        $mods = <<<EOM
<mods xmlns="http://www.loc.gov/mods/v3" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <titleInfo>
    <title>{$title}</title>
  </titleInfo>
</mods>
EOM;
        // @codingStandardsIgnoreEnd

        $mods_path = $this->dir . DIRECTORY_SEPARATOR . 'MODS.xml';
        if (!file_exists($mods_path)) {
            file_put_contents($mods_path, $mods);
            $this->log->addInfo("CreateModsStub plugin creating MODS file " . $mods_path);
        } else {
            $this->log->addInfo("CreateModsStub plugin detected a MODS file at " . $mods_path . ", not replacing it.");
        }
    }
}
