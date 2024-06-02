<?php

namespace App\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;
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
            if (!$this->isWindows && (time() - $this->ts > 1500)) {
                break;
            }

            $cat_url = "https://www.{$this->option('domain')}/$pl/";

            $html = $this->getURLWithDB(
                "$cat_url#$pl",
                [
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Host' => "www.{$this->option('domain')}",
                    'User-Agent' => "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)",
                ],
                'coding_school',
                [
                    'kk' => $cat_url,
                ]
            );

            if ($html === null) {
                continue;
            }

            $crawler = new Crawler($html);

            $tree = [];
            $current_h2 = null;
            $current_a = null;
            $crawler->filter('#leftmenuinnerinner')->children()->each(function (Crawler $tag) use (&$tree, &$current_h2, &$current_a, $cat_url) {
                if (!$this->isWindows && (time() - $this->ts > 1500)) {
                    return;
                }
                if ($tag->nodeName() === 'h2') {
                    $current_h2 = $tag->text();
                    $tree[$current_h2] = [];
                } elseif ($tag->nodeName() === 'a') {
                    $current_a = $tag->text();

                    $x2crawler = new Crawler($this->getHref($tag, $cat_url));
                    $x2h1 = $x2crawler->filter('h1')->text();
                    $tree[$current_h2][$current_a] = $x2h1;
                } elseif ($tag->nodeName() === 'div') {
                    if ($tag->attr('class') === 'ref_overview' || $tag->attr('class') === 'tut_overview') {
                        $tree[$current_h2][$current_a] = [];
                        $tag->children()->each(function (Crawler $a) use (&$tree, &$current_h2, &$current_a, $cat_url) {
                            if (!$this->isWindows && (time() - $this->ts > 1500)) {
                                return;
                            }
                            if ($a->nodeName() === 'br') {
                                return;
                            }
                            if ($a->nodeName() === 'span') {
                                $this->warn("$current_h2, $current_a, {$a->text()}");
                                return;
                            }
                            if ($a->nodeName() !== 'a') {
                                dd($current_h2, $current_a, $a->nodeName());
                            }
                            $tree[$current_h2][$current_a][$a->text()] = null;

                            $x3crawler = new Crawler($this->getHref($a, $cat_url));
                            $x3h1 = $x3crawler->filter('h1')->text();
                            $tree[$current_h2][$current_a][$a->text()] = $x3h1;
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

            file_put_contents(database_path("/$pl.yaml"), Yaml::dump($tree, 5), LOCK_EX);
        }
    }

    private function getHref(Crawler $tag, string $baseURL): string|null
    {
        $href = $tag->attr('href');
        $getURL = $baseURL . $href;

        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            $getURL = "https://" . parse_url($baseURL, PHP_URL_HOST) . $href;
        }
        if (str_starts_with($href, 'https://') || str_starts_with($href, 'http://')) {
            $this->warn("__LINE__:" . __LINE__ . "[$href]");
            return null;
        }

        return $this->getURLWithDB(
            $getURL . "#$href",
            [
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate',
                'Host' => "www.{$this->option('domain')}",
                'User-Agent' => "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)",
            ],
            'coding_school',
            [
                'kk' => $getURL,
            ]
        );
    }
}
