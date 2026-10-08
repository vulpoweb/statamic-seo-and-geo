<?php

namespace Vulpo\Seo\Schema;

/**
 * One node in the JSON-LD graph.
 *
 * Returning null is a first-class answer: a node whose minimum viable data is
 * missing should disappear rather than be emitted incomplete, because an
 * invalid node costs the whole page its rich result while an absent one costs
 * only itself.
 */
interface SchemaNode
{
    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array;
}
