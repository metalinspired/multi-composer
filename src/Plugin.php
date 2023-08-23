<?php

declare(strict_types=1);

namespace metalinspired\MultiComposer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\Link;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;
use Composer\Semver\Constraint\Constraint;
use Exception;

use function array_key_exists;
use function array_keys;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;

class Plugin implements PluginInterface
{
    /**
     * {@inheritDoc}
     * @throws PluginException
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $extra = $composer->getPackage()->getExtra();

        if (empty($extra['multi-composer'])) {
            return;
        }

        $config = $extra['multi-composer'];

        if (!is_array($config)) {
            throw new PluginException('Configuration is not an array');
        }

        $repositoryManager = $composer->getRepositoryManager();

        foreach ($config as $index => $configItem) {
            if (empty($configItem['package'])) {
                throw new PluginException("index $index: Package not set");
            }

            if (!is_string($configItem['package'])) {
                throw new PluginException("index $index: Package must be a string");
            }

            $packageNameAndVersion = $configItem['package'];
            $packageInfo = explode(':', $packageNameAndVersion, 2);

            if (count($packageInfo) !== 2) {
                throw new PluginException("index $index: Package not defined as package-name:version");
            }

            [$packageName, $packageVersion] = $packageInfo;

            /** @var ?Package $package */
            $package = $repositoryManager->findPackage($packageName, $packageVersion);

            if ($package === null) {
                throw new PluginException("Could not find '$packageNameAndVersion'");
            }

            $distUrl = $package->getDistUrl();

            if ($distUrl === null) {
                throw new PluginException("Distribution URL of '$packageNameAndVersion' is null");
            }

            if (!is_dir($distUrl)) {
                throw new PluginException("Distribution URL of '$packageNameAndVersion' is not a local directory");
            }

            $lockFile = new JsonFile($distUrl . '/composer.lock', null, $io);

            try {
                $lockData = $lockFile->read();
            } catch (Exception $e) {
                throw new PluginException("Error loading '$packageNameAndVersion': " . $e->getMessage());
            }

            $skipDev = array_key_exists('skip_dev', $configItem) && $configItem['skip_dev'];
            $loader = new ArrayLoader();
            $lockPackages = $lockData['packages'];

            if (!$skipDev && isset($lockData['packages-dev'])) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $lockPackages = array_merge($lockPackages, $lockData['packages-dev']);
            }

            if (!isset($lockPackages[0]['name'])) {
                throw new PluginException("composer.lock is invalid for '$packageNameAndVersion'");
            }

            /** @var BasePackage[] $packages */
            $packages = [];

            foreach ($lockPackages as $info) {
                $lockPackage = $loader->load($info);
                $packages[$lockPackage->getName()] = $lockPackage;

                if ($lockPackage instanceof AliasPackage) {
                    $aliasOf = $lockPackage->getAliasOf();

                    $packages[$aliasOf->getName()] = $aliasOf;
                }
            }

            [$provides, $conflicts] = $this->getProvidesAndConflicts(
                $packages,
                $packageName,
                array_keys($package->getDevRequires())
            );

            $package->setProvides($provides);
            $package->setConflicts($conflicts);

            if (!array_key_exists('autoload_psr-4', $configItem) || !$configItem['autoload_psr-4']) {
                $autoload = $package->getAutoload();

                if (array_key_exists('psr-4', $autoload)) {
                    unset($autoload['psr-4']);

                    $package->setAutoload($autoload);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @param BasePackage[] $packages
     * @param string $source
     * @param string[] $sourceDevRequires
     * @return array
     */
    private function getProvidesAndConflicts(array $packages, string $source, array $sourceDevRequires): array
    {
        $provides = [];
        $conflicts = [];

        foreach ($packages as $package) {
            $name = $package->getName();
            $constraint = new Constraint('=', $package->getVersion());
            $prettyVersion = $package->getPrettyVersion();

            $provides[$name] = new Link(
                $source,
                $name,
                $constraint,
                Link::TYPE_PROVIDE,
                $prettyVersion
            );

            if (!in_array($name, $sourceDevRequires, true)) {
                $conflicts[$name] = new Link(
                    $source,
                    $name,
                    $constraint,
                    Link::TYPE_CONFLICT,
                    "!=$prettyVersion"
                );
            }
        }

        return [$provides, $conflicts];
    }
}
