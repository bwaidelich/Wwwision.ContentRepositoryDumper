<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Utility\Files;
use SebastianBergmann\Diff\Differ;
use Wwwision\ContentRepositoryDumper\Exporter;
use Wwwision\ContentRepositoryDumper\Model\ContentDimensionCoordinates;
use Wwwision\ContentRepositoryDumper\Model\ContentDimensionFilter;
use Wwwision\ContentRepositoryDumper\Model\Options;

/**
 * CLI command controller for the Content Repository Dumper
 */
final class CrCommandController extends CommandController
{

    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly Exporter $exporter,
    )
    {
        parent::__construct();
    }

    /**
     * Dump the structure of the Content Repository to flat files in order to debug/compare them
     *
     * @param string $site Name of the site node to dump (e.g. "neosdemo")
     * @param string|null $dimensions The dimensions to export in the format: "<key1>:<value1>,<value2>;<key2>:<value1>,...;..." (e.g. "language:de,en_UK;market:us"). If omitted, all dimensions will be included
     * @param string|null $path Path to dump the file(s) to. This has to be the full path to an empty directory. If omitted, files are dumped to "/Data/ContentRepositoryDump/<Timestamp>"
     * @return void
     */
    public function dumpCommand(string $site, string $dimensions = null, string $path = null): void
    {
        $siteEntity = $this->siteRepository->findOneByNodeName($site);
        if ($siteEntity === null) {
            throw new \InvalidArgumentException(sprintf('Failed to find site for node name "%s"', $site));
        }

        $path ??= Files::concatenatePaths([FLOW_PATH_DATA, 'ContentRepositoryDump', time()]);
        $this->verifyPath($path);
        $batches = ($this->exporter)($siteEntity, new Options(dimensionFilter: ContentDimensionFilter::parseString($dimensions ?? '')));
        $filesCount = 0;
        $this->output->progressStart();
        foreach ($batches as $batch) {
            $filename = Files::concatenatePaths([$path, $this->coordinatesFilename($batch->coordinates)]);
            foreach ($batch->nodes as $node) {
                $attributes = [];
                if ($node->isTethered) {
                    $attributes[] = 'tethered';
                }
                if ($node->isHidden) {
                    $attributes[] = 'hidden';
                }
                $attributesString = $attributes !== [] ? ' [' . implode('|', $attributes) . ']' : '';
                file_put_contents($filename, str_repeat(' ', $node->level * 2) . $node->id . ' (' . $node->name . ')' . $attributesString . PHP_EOL, FILE_APPEND);
                $this->output->progressAdvance();
            }
            $filesCount ++;
        }
        $this->output->progressFinish();
        $this->outputLine();
        $this->outputLine('<success>Dumped Content Repository to %d file%s at "%s"</success>', [$filesCount, $filesCount !== 1 ? 's' : '', $path]);
    }

    /**
     * Compares the files created by a previous "cr:dump" call
     *
     * @param string $path1 Absolute path to the dumped files of the first installation (e.g. ".../Data/ContentRepositoryDump/1672995687")
     * @param string $path2 Absolute path to the dumped files of the second installation (e.g. ".../Data/ContentRepositoryDump/1672995788")
     * @param bool $verbose If set the difference between files will be outputted
     * @return void
     */
    public function compareDumpsCommand(string $path1, string $path2, bool $verbose = false): void
    {
        $filePaths1 = iterator_to_array(Files::getRecursiveDirectoryGenerator($path1, '.txt'));
        $filePaths2 = iterator_to_array(Files::getRecursiveDirectoryGenerator($path2, '.txt'));

        $filesOnlyInPath1 = [];
        foreach ($filePaths1 as $filePath1) {
            $filename = basename($filePath1);
            $filePath2 = Files::concatenatePaths([$path2, $filename]);
            if (!file_exists($filePath2)) {
                $filesOnlyInPath1[] = $filename;
                continue;
            }
            $this->output(' %s... ', [$filename]);
            $fileContents1 = file_get_contents($filePath1);
            $fileContents2 = file_get_contents($filePath2);
            if ($fileContents1 === $fileContents2) {
                $this->outputLine('<success>✔</success>');
                continue;
            }
            $this->outputLine('<error>✖</error>');
            if ($verbose) {
                $differ = new Differ();
                $this->outputLine($differ->diff($fileContents1, $fileContents2));
            }
        }
    }

    /** --------------------------- */

    private function verifyPath(string $path): void
    {
        if (!is_dir($path)) {
            Files::createDirectoryRecursively($path);
        }
        if (!is_dir($path)) {
            throw new \RuntimeException(sprintf('The target path "%s" is no directory', $path), 1672829437);
        }
        if (!is_writable($path)) {
            throw new \RuntimeException(sprintf('The target path "%s" is not writable', $path), 1672829488);
        }
        if (count(scandir($path)) !== 2) {
            throw new \RuntimeException(sprintf('The target path "%s" is not empty', $path), 1672829384);
        }
    }


    private function coordinatesFilename(ContentDimensionCoordinates $coordinates): string
    {
        $rules = <<<'RULES'
            :: Any-Latin;
            :: NFD;
            :: [:Nonspacing Mark:] Remove;
            :: NFC;
            :: [^-[:^Punctuation:]] Remove;
            :: Lower();
            [:^L:] { [-] > ;
            [-] } [:^L:] > ;
            [-[:Separator:]]+ > '-';
        RULES;

        $transliterator = \Transliterator::createFromRules($rules);
        if (!$transliterator instanceof \Transliterator) {
            throw new \RuntimeException('Failed to instantiate Transliterator', 1672829663);
        }
        $parts = [];
        foreach ($coordinates->coordinates as $key => $value) {
            $parts[] = $transliterator->transliterate($key) . '_' . $transliterator->transliterate($value);
        }
        return implode('-', $parts) . '.txt';
    }

}
