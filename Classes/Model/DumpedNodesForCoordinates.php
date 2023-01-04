<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Model;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class DumpedNodesForCoordinates
{

    public function __construct(
        public readonly ContentDimensionCoordinates $coordinates,
        public readonly DumpedNodes $nodes,
    ) {}

}
