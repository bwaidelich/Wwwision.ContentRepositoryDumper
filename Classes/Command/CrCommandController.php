<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Utility\Files;
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
