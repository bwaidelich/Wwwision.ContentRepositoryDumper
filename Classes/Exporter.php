<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper;

use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\Site;
use Wwwision\ContentRepositoryDumper\Model\ContentDimensionCoordinates;
use Wwwision\ContentRepositoryDumper\Model\DumpedNode;
use Wwwision\ContentRepositoryDumper\Model\DumpedNodes;
use Wwwision\ContentRepositoryDumper\Model\DumpedNodesForCoordinates;
use Wwwision\ContentRepositoryDumper\Model\Options;

#[Flow\Scope("singleton")]
final class Exporter
{

    public function __construct(
        private readonly ContextFactoryInterface $contextFactory,
        private readonly ContentDimensionCombinator $contentDimensionCombinator,
    ) {}

    /**
     * @param Site $site
     * @param Options $options
     * @return \Generator<DumpedNodesForCoordinates>
     */
    public function __invoke(Site $site, Options $options): \Generator
    {
        foreach ($this->contentDimensionCombinator->getAllAllowedCombinations() as $combination) {
            $coordinates = new ContentDimensionCoordinates(array_map(static fn ($value) => is_array($value) ? $value[0] : $value, $combination));
            if (!$options->dimensionFilter->matchesContentDimension($coordinates)) {
                continue;
            }
            yield new DumpedNodesForCoordinates($coordinates, $this->exportSite($site, $combination));

        }
    }

    private function exportSite(Site $site, array $dimensions): DumpedNodes
    {
        $context = $this->contextFactory->create([
            'currentSite' => $site,
            'dimensions' => $dimensions,
        ]);
        $siteNode = $context->getNode('/sites/' . $site->getNodeName());
        if (!$siteNode instanceof TraversableNodeInterface) {
            throw new \RuntimeException(sprintf('Failed to find site node for site name "%s" in dimension %s', $site->getNodeName(), json_encode($dimensions)));
        }
        return new DumpedNodes($this->exportNodes($siteNode, 0));
    }

    private function exportNodes(TraversableNodeInterface $node, int $level): \Generator
    {
        yield new DumpedNode((string)$node->getNodeAggregateIdentifier(), (string)$node->getNodeName(), $level);
        foreach ($node->findChildNodes() as $childNode) {
            yield from $this->exportNodes($childNode, $level + 1);
        }
    }

}
