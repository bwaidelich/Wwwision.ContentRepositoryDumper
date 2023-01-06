<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Model;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class DumpedNode
{

    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly bool $isTethered,
        public readonly bool $isHidden,
        public readonly int $level,
    ) {}

}
