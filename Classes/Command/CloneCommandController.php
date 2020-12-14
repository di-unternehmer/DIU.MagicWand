<?php
namespace Sitegeist\MagicWand\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Neos\Flow\Core\Bootstrap;
use Sitegeist\MagicWand\DBAL\SimpleDBAL;
use Symfony\Component\Yaml\Yaml;
use Sitegeist\MagicWand\Service\LambdaService;

/**
 * @Flow\Scope("singleton")
 */
class CloneCommandController extends AbstractCommandController
{

    /**
     * @Flow\InjectConfiguration(package="Sitegeist.MagicWand", path="aws.enabled")
     * @var boolean
     */
    protected $awsEnabled;


    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var string
     * @Flow\InjectConfiguration("clonePresets")
     */
    protected $clonePresets;

    /**
     * @var string
     * @Flow\InjectConfiguration("defaultPreset")
     */
    protected $defaultPreset;

    /**
     * @Flow\Inject
     * @var SimpleDBAL
     */
    protected $dbal;

    /**
     * @Flow\Inject
     * @var LambdaService
     */
    protected $lambdaService;

    /**
     * Show the list of predefined clone configurations
     */
    public function listCommand()
    {
        if ($this->clonePresets) {
            foreach ($this->clonePresets as $presetName => $presetConfiguration) {
                $this->renderHeadLine($presetName);
                $presetConfigurationAsYaml = Yaml::dump($presetConfiguration);
                $lines = explode(PHP_EOL, $presetConfigurationAsYaml);
                foreach ($lines as $line) {
                    $this->renderLine($line);
                }
            }
        }
    }
    /**
     * Clone a flow setup as specified in Settings.yaml (Sitegeist.MagicWand.clonePresets ...)
     *
     * @param string $presetName name of the preset from the settings
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     */
    public function presetCommand($presetName, $yes = false, $keepDb = false)
    {
        if (count($this->clonePresets) > 0) {
            if ($this->clonePresets && array_key_exists($presetName, $this->clonePresets)) {

                $this->configurationService->setCurrentPreset($presetName);
                $configuration = $this->configurationService->getCurrentConfiguration();

                $this->renderLine('Clone by preset ' . $presetName);
                $this->importRemoteDump(
                    (isset($configuration['postClone']) ?
                        $configuration['postClone'] : null
                    ),
                    $yes,
                    $keepDb
                );
            } else {
                $this->renderLine('The preset ' . $presetName . ' was not found!');
                $this->quit(1);
            }
        } else {
            $this->renderLine('No presets found!');
            $this->quit(1);
        }
    }

    protected function importRemoteDump(
        $postClone = null,
        $yes = false,
        $keepDb = false
    )
    {

        #################
        # Are you sure? #
        #################

        if (!$yes) {
            $this->renderLine("Are you sure you want to do this?  Type 'yes' to continue: ");
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if (trim($line) != 'yes') {
                $this->renderLine('exit');
                $this->quit(1);
            } else {
                $this->renderLine();
                $this->renderLine();
            }
        }

        ######################
        # Measure Start Time #
        ######################
        $startTimestamp = time();

        ########################
        # Drop and Recreate DB #
        ########################
        if ($keepDb == false) {
            $this->renderHeadLine('Drop and Recreate DB');

            $emptyLocalDbSql = $this->dbal->flushDbSql($this->databaseConfiguration['driver'], $this->databaseConfiguration['dbname']);

            $this->executeLocalShellCommand(
                'echo %s | %s',
                [
                    escapeshellarg($emptyLocalDbSql),
                    $this->dbal->buildCmd(
                        $this->databaseConfiguration['driver'],
                        $this->databaseConfiguration['host'],
                        (int)$this->databaseConfiguration['port'],
                        $this->databaseConfiguration['user'],
                        $this->databaseConfiguration['password'],
                        $this->databaseConfiguration['dbname']
                    )
                ]
            );
        } else {
            $this->renderHeadLine('Skipped (Drop and Recreate DB)');
        }

        ######################
        #  Transfer Database #
        ######################
        $this->renderHeadLine('Transfer Database');
        $file = $this->lambdaService->getLambdaContent();
        $this->executeLocalShellCommand(
            'cat '.$file.' | %s',
            [
                $this->dbal->buildCmd(
                    $this->databaseConfiguration['driver'],
                    $this->databaseConfiguration['host'],
                    (int)$this->databaseConfiguration['port'],
                    $this->databaseConfiguration['user'],
                    $this->databaseConfiguration['password'],
                    $this->databaseConfiguration['dbname']
                )
            ]
        );

        ####################################
        # Check resourceProxyConfiguration #
        ####################################

        $resourceProxyConfiguration = $this->configurationService->getCurrentConfigurationByPath('resourceProxy');

        if (!$resourceProxyConfiguration) {
            $this->renderHeadLine( 'resourceProxyConfiguration not found!');
        }


        ################
        # Clear Caches #
        ################

        $this->renderHeadLine('Clear Caches');
        $this->executeLocalFlowCommand('flow:cache:flush');

        ##################
        # Set DB charset #
        ##################
        if ($this->databaseConfiguration['driver'] == 'pdo_mysql') {
            $this->renderHeadLine('Set DB charset');
            $this->executeLocalFlowCommand('database:setcharset');
        }

        ##############
        # Migrate DB #
        ##############

        $this->renderHeadLine('Migrate cloned DB');
        $this->executeLocalFlowCommand('doctrine:migrate');

        #####################
        # Publish Resources #
        #####################

        $this->renderHeadLine('Publish Resources');
        $this->executeLocalFlowCommand('resource:publish');

        ##############
        # Post Clone #
        ##############

        if ($postClone) {
            $this->renderHeadLine('Execute post_clone commands');
            if (is_array($postClone)) {
                foreach ($postClone as $postCloneCommand) {
                    $this->executeLocalShellCommandWithFlowContext($postCloneCommand);
                }
            } else {
                $this->executeLocalShellCommandWithFlowContext($postClone);
            }
        }

        #################
        # Final Message #
        #################

        $endTimestamp = time();
        $duration = $endTimestamp - $startTimestamp;

        $this->renderHeadLine('Done');
        $this->renderLine('Successfully cloned in %s seconds', [$duration]);
    }


}
