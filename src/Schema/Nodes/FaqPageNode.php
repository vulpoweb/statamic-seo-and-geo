<?php

namespace Vulpo\Seo\Schema\Nodes;

use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Schema\Support\Normalize;

final class FaqPageNode implements SchemaNode
{
    /** @var array<int, array{question: string, answer: string}> */
    private array $questions = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Straight off a grid field, which is where the questions already are on
     * most sites -- an accordion block, typically. Retyping them into a second
     * field is how the two drift apart.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function fromRows(array $rows, string $questionKey = 'question', string $answerKey = 'answer'): self
    {
        $node = new self;

        foreach ($rows as $row) {
            $question = $row[$questionKey] ?? null;
            $answer = $row[$answerKey] ?? null;

            if (is_scalar($question) && is_scalar($answer)) {
                $node->question((string) $question, (string) $answer);
            }
        }

        return $node;
    }

    public function question(string $question, string $answer): self
    {
        $question = Normalize::text($question);
        $answer = Normalize::text($answer);

        if ($question !== null && $answer !== null) {
            $this->questions[] = ['question' => $question, 'answer' => $answer];
        }

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        if ($this->questions === []) {
            return null;
        }

        return Normalize::compact([
            '@type' => 'FAQPage',
            '@id' => $context->fragment('faq'),
            'inLanguage' => $context->language(),
            'mainEntity' => array_map(fn (array $row) => [
                '@type' => 'Question',
                'name' => $row['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $row['answer']],
            ], $this->questions),
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->questions === [];
    }
}
