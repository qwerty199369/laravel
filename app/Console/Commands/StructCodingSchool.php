<?php

namespace App\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\DomCrawler\Crawler;
use Webmozart\Assert\Assert;

class StructCodingSchool extends BaseBot
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'struct:coding-school {--domain=} {--sleep=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private int $ts;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->ts = time();

        Assert::notNull($this->option('domain'));

        if (!Schema::hasTable('coding_school')) {
            Schema::create('coding_school', function (Blueprint $table) {
                $table->string('kk')->unique()->primary();
                $table->text('vv');
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
            });
        }

        $pls = [
            'html',
            'css',
            'js',
            'php',
            'sql',
            'python',
            'java',
            'c',
            'cpp',
            'cs',
        ];
        foreach ($pls as $pl) {
            if (time() - $this->ts > 3000) {
                break;
            }

            $loc = "https://www.{$this->option('domain')}/$pl/";

            $html = $this->getURLWithDB(
                "$loc#$pl",
                [
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Host' => "www.{$this->option('domain')}",
                    'User-Agent' => "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)",
                ],
                'coding_school',
                [
                    'kk' => $loc,
                ]
            );

            if ($html === null) {
                continue;
            }

            $crawler = new Crawler($html);

            $tree = [];
            $current_h2 = null;
            $current_a = null;
            $crawler->filter('#leftmenuinnerinner')->children()->each(function (Crawler $tag) use (&$tree, &$current_h2, &$current_a) {
                if ($tag->nodeName() === 'h2') {
                    $current_h2 = $tag->text();
                    $tree[$current_h2] = [];
                } elseif ($tag->nodeName() === 'a') {
                    $current_a = $tag->text();
                    $tree[$current_h2][$current_a] = null;
                } elseif ($tag->nodeName() === 'div') {
                    if ($tag->attr('class') === 'ref_overview' || $tag->attr('class') === 'tut_overview') {
                        $tree[$current_h2][$current_a] = [];
                        $tag->children()->each(function (Crawler $a) use (&$tree, &$current_h2, &$current_a) {
                            if ($a->nodeName() !== 'a') {
                                dd($a->nodeName());
                            }
                            $tree[$current_h2][$current_a][] = $a->text();
                        });
                    } else {
                        dd($tag->attr('class'));
                    }
                } elseif ($tag->nodeName() === 'br') {
                    return;
                } elseif ($tag->nodeName() === 'br') {
                    dd($tag->nodeName());
                }
            });

            dd($tree);
            break;
        }
    }
}
