<?php
declare(strict_types=1);
namespace Wwwision\ContentRepositoryDumper\Model;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

#[Flow\Proxy(false)]
final class ContentDimensionFilter
{

    private function __construct(
        private readonly array $parts
    ) {}

    public static function parseString(string $string): self
    {
        $parts = [];
        $segments = Arrays::trimExplode(';', $string);
        foreach ($segments as $segment) {
            [$name, $valuesString] = Arrays::trimExplode(':', $segment);
            $values = Arrays::trimExplode(',', $valuesString);
            $parts[$name] = $values;
        }
        return new self($parts);
    }

    public function matchesContentDimension(ContentDimensionCoordinates $dimensionSpacePoint): bool
    {
        foreach ($dimensionSpacePoint->coordinates as $name => $value) {
            if (!isset($this->parts[$name])) {
                continue;
            }
            if (!in_array($value, $this->parts[$name], true)) {
                return false;
            }
        }
        return true;
    }

}
