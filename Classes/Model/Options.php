<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Model;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Options
{

    public function __construct(
        public readonly ContentDimensionFilter $dimensionFilter,
    ) {}

    public function with(
        ?ContentDimensionFilter $dimensionFilter = null,
    ): self
    {
        return new self(
            $dimensionFilter ?? $this->dimensionFilter,
        );
    }

}
