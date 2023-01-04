<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Model;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class ContentDimensionCoordinates
{

    public function __construct(
        public readonly array $coordinates
    ) {}
}
