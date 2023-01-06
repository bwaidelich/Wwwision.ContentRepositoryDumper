<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodePath;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\Projection\NodeHiddenState\NodeHiddenStateFinder;
use Neos\ContentRepository\Core\Projection\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
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
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
    ) {}

    /**
     * @param Site $site
     * @param Options $options
     * @return \Generator<DumpedNodesForCoordinates>
     */
    public function __invoke(Site $site, Options $options): \Generator
    {
        $contentRepositoryIdentifier = ContentRepositoryId::fromString($site->getConfiguration()['contentRepository'] ?? throw new \RuntimeException('There is no content repository identifier configured in Sites configuration in Settings.yaml: Neos.Neos.sites.*.contentRepository', 1672831790));
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryIdentifier);
        $liveWorkspace = $contentRepository->getWorkspaceFinder()->findOneByName(WorkspaceName::forLive());
        if ($liveWorkspace === null) {
            throw new \InvalidArgumentException(sprintf('Failed to find live workspace for Content Repository "%s"', $contentRepositoryIdentifier->value));
        }
        try {
            $sitesNode = $contentRepository->getContentGraph()->findRootNodeAggregateByType($liveWorkspace->currentContentStreamId, NodeTypeName::fromString('Neos.Neos:Sites'));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Failed to find sites root node for Content Repository "%s" and content stream "%s"', $contentRepositoryIdentifier->value, $liveWorkspace->currentContentStreamId->getValue()), 1667236125, $e);
        }

        /** @var DimensionSpacePoint $dimensionSpacePoint */
        foreach ($contentRepository->getVariationGraph()->getDimensionSpacePoints() as $dimensionSpacePoint) {
            $coordinates = new ContentDimensionCoordinates($dimensionSpacePoint->coordinates);
            if (!$options->dimensionFilter->matchesContentDimension($coordinates)) {
                continue;
            }
            yield new DumpedNodesForCoordinates($coordinates, $this->exportSite($site, $contentRepository, $liveWorkspace, $dimensionSpacePoint, $sitesNode));
        }
    }

    private function exportSite(Site $site, ContentRepository $contentRepository, Workspace $liveWorkspace, DimensionSpacePoint $dimensionSpacePoint, NodeAggregate $sitesNode): DumpedNodes
    {
        $contentSubGraph = $contentRepository->getContentGraph()->getSubgraph($liveWorkspace->currentContentStreamId, $dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());
        $nodeHiddenStateFinder = $contentRepository->projectionState(NodeHiddenStateFinder::class);

        $siteNodePath = NodePath::fromString($site->getNodeName()->value);
        $siteNode = $contentSubGraph->findNodeByPath($siteNodePath, $sitesNode->nodeAggregateId);
        if ($siteNode === null) {
            throw new \RuntimeException(sprintf('Failed to find site node with path "%s" underneath "%s"', $siteNodePath->jsonSerialize(), $sitesNode->nodeAggregateId->getValue()), 1667814855);
        }
        return new DumpedNodes($this->exportNodes($contentSubGraph, $nodeHiddenStateFinder, $siteNode, 0));
    }

    private function exportNodes(ContentSubgraphInterface $contentSubGraph, NodeHiddenStateFinder $hiddenStateFinder, Node $node, int $level): \Generator
    {
        $hiddenState = $hiddenStateFinder->findHiddenState($node->subgraphIdentity->contentStreamId, $node->subgraphIdentity->dimensionSpacePoint, $node->nodeAggregateId);
        yield new DumpedNode($node->nodeAggregateId->getValue(), $node->nodeName->jsonSerialize(), $node->classification->isTethered(), $hiddenState->isHidden, $level);
        foreach ($contentSubGraph->findChildNodes($node->nodeAggregateId, FindChildNodesFilter::all()) as $childNode) {
            yield from $this->exportNodes($contentSubGraph, $hiddenStateFinder, $childNode, $level + 1);
        }
    }

}
