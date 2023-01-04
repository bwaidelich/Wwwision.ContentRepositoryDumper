<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Model;

use Neos\Flow\Annotations as Flow;

/**
 * @implements \IteratorAggregate<DumpedNode>
 */
#[Flow\Proxy(false)]
final class DumpedNodes implements \IteratorAggregate
{

    public function __construct(
        private readonly \Generator $generator
    ) {}


    /**
     * @return \Traversable<DumpedNode>|DumpedNode[]
     */
    public function getIterator(): \Traversable
    {
        return yield from $this->generator;
    }
}
