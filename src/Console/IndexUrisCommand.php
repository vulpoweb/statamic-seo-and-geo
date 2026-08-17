<?php

namespace Vulpo\Seo\Console;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Vulpo\Seo\Redirects\UriLedger;

/**
 * Records where every page currently lives, which is what automatic redirects
 * compare against. Run once after installing the addon; after that it maintains
 * itself.
 */
class IndexUrisCommand extends Command
{
    protected $signature = 'vulpo:seo:index-uris';

    protected $description = 'Record the current URL of every entry, so URL changes can create redirects';

    use RunsInPlease;

    public function handle(UriLedger $ledger): int
    {
        $count = $ledger->prime();

        $this->components->info("Indexed {$count} URLs.");

        return self::SUCCESS;
    }
}
