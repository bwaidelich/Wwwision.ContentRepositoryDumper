<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper;

use Neos\ContentRepository\Domain\Model\Node;
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
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true,
        ]);
        $siteNode = $context->getNode('/sites/' . $site->getNodeName());
        if (!$siteNode instanceof Node) {
            throw new \RuntimeException(sprintf('Failed to find site node for site name "%s" in dimension %s', $site->getNodeName(), json_encode($dimensions)));
        }
        return new DumpedNodes($this->exportNodes($siteNode, 0));
    }

    private function exportNodes(Node $node, int $level): \Generator
    {
        yield new DumpedNode((string)$node->getNodeAggregateIdentifier(), (string)$node->getNodeName(), $node->isTethered(), $node->isHidden(), $level);

        // NOTE: findChildNodes() returns the nodes ordered by their sortingIndex. In Neos < 9 it can happen that
        // two nodes have the same sorting index (and parent node and dimensions).
        // In order to achieve a deterministic ordering nevertheless, we sort nodes with the same index by their name:
        $nodesOnThisLevel = [];
        foreach ($node->findChildNodes() as $childNode) {
            $nodesOnThisLevel[] = $childNode;
        }
        usort($nodesOnThisLevel, static function (Node $node1, Node $node2) {
            $index1 = $node1->getNodeData()->getIndex();
            $index2 = $node2->getNodeData()->getIndex();
            if ($index1 === $index2) {
                return (string)$node1->getNodeName() <=> (string)$node2->getNodeName();
            }
            return $index1 <=> $index2;
        });

        foreach ($nodesOnThisLevel as $childNode) {
            yield from $this->exportNodes($childNode, $level + 1);
        }
    }

}
