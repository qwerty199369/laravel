<?php

namespace App\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;
use Webmozart\Assert\Assert;

class StructCodingSchool extends BaseBot
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'struct:coding-school {--domain=} {--sleep=} {--trans=} {--aigc}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private int $ts;

    private string $dir = PHP_OS_FAMILY === 'Windows'
        ? 'D:/repos/lfsfiles_alpha/coding-school'
        : __DIR__ . '/../../../../lfsrepo/coding-school';

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

        $this->warn(DB::table('coding_school')->where('vv', '__timeout__')->delete());

        $pls = [
//            'html',
//            'css',
//            'js',
//            'php',
//            'sql',
//            'python',
//            'java',
//            'c',
//            'cpp',
//            'cs',
            'rust',
        ];
        foreach ($pls as $pl) {
            if (!$this->isWindows && (time() - $this->ts > 500)) {
                break;
            }

            $plfile = "$this->dir/$pl.yaml";

            if (file_exists($plfile)) {
                goto trans;
            }

            if (method_exists($this, "crawl_$pl")) {
                $tree = $this->{"crawl_$pl"}();
            } else {
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
                    if (!$this->isWindows && (time() - $this->ts > 500)) {
                        return;
                    }
                    if ($tag->nodeName() === 'h2') {
                        $current_h2 = $tag->text();
                        $tree[$current_h2] = [];
                    } elseif ($tag->nodeName() === 'a') {
                        $current_a = $tag->text();

                        $x2crawler = new Crawler($this->getHref($tag, $cat_url));
                        $x2h1 = $x2crawler->filter('h1')->text();
                        $tree[$current_h2][$x2h1] = null;
                    } elseif ($tag->nodeName() === 'div') {
                        if ($tag->attr('class') === 'ref_overview' || $tag->attr('class') === 'tut_overview') {
                            $tree[$current_h2][$current_a] = [];
                            $tag->children()->each(function (Crawler $a) use (&$tree, &$current_h2, &$current_a, $cat_url) {
                                if (!$this->isWindows && (time() - $this->ts > 500)) {
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

                                $x3crawler = new Crawler($this->getHref($a, $cat_url));
                                $x3h1 = $x3crawler->filter('h1')->text();
                                $tree[$current_h2][$current_a][$x3h1] = null;
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
            }

            file_put_contents($plfile, Yaml::dump($tree, 5), LOCK_EX);

            trans:

            if (pf_is_string_filled($this->option('trans'))) {
                $plkv = Yaml::parseFile($plfile);

                foreach ($plkv as $kk => &$vv) {
                    if ($vv === null || $vv === '' || (pf_is_string_filled($vv) && $this->option('trans') === 'force')) {
                        $vv = (new AsciiSlugger())
                            ->slug($this->translate($kk, 'zh-CN', 'en'))
                            ->lower()
                            ->toString();
                    } elseif (is_array($vv)) {
                        foreach ($vv as $kkk => &$vvv) {
                            if ($vvv === null || $vvv === '' || (pf_is_string_filled($vvv) && $this->option('trans') === 'force')) {
                                $vvv = (new AsciiSlugger())
                                    ->slug($this->translate($kkk, 'zh-CN', 'en'))
                                    ->lower()
                                    ->toString();
                            } elseif (is_array($vvv)) {
                                foreach ($vvv as $kkkk => &$vvvv) {
                                    if ($vvvv === null || $vvvv === '' || (pf_is_string_filled($vvvv) && $this->option('trans') === 'force')) {
                                        $vvvv = (new AsciiSlugger())
                                            ->slug($this->translate($kkkk, 'zh-CN', 'en'))
                                            ->lower()
                                            ->toString();
                                    }
                                }
                                unset($vvvv);
                            }
                        }
                        unset($vvv);
                    }
                }
                unset($vv);

                file_put_contents($plfile, Yaml::dump($plkv, 5), LOCK_EX);
            }

            if ($this->option('aigc')) {
                $plkv = Yaml::parseFile($plfile);

                $this->fs()->mkdir("$this->dir/$pl/");

                foreach ($plkv as $kk => $vv) {
                    if (pf_is_string_filled($vv)) {
                        $this->aigc($kk, "$this->dir/$pl/$vv.md", null, null);
                    } elseif (is_array($vv)) {
                        foreach ($vv as $kkk => $vvv) {
                            if (pf_is_string_filled($vvv)) {
                                $this->aigc($kkk, "$this->dir/$pl/$vvv.md", $kk, null);
                            } elseif (is_array($vvv)) {
                                foreach ($vvv as $kkkk => $vvvv) {
                                    if (pf_is_string_filled($vvvv)) {
                                        $this->aigc($kkkk, "$this->dir/$pl/$vvvv.md", $kk, $kkk);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function crawl_rust(): array
    {
        $html = $this->getURL(
            "https://kaisery.github.io/trpl-zh-cn/title-page.html",
            [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'Accept-Language' => 'zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2',
                'Connection' => 'keep-alive',
                'Host' => "kaisery.github.io",
                'User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0",
            ]
        );

        $crawler = new Crawler($html);

        $tree = [];
        $current_li = null;
        $crawler->filter('.sidebar-scrollbox > ol > li')->each(function (Crawler $li) use (&$tree, &$current_li) {
            if ($li->filter('ol')->count() === 0) {
                $current_li = $li->text();
                $tree[$current_li] = null;
            } elseif ($li->filter('ol')->count() === 1) {
                $li->filter('ol li')->each(function (Crawler $li2) use (&$tree, $current_li) {
                    $tree[$current_li][$li2->text()] = null;
                });
            } else {
                dd($li->filter('ol')->count());
            }
        });

        return $tree;
    }

    private function aigc(string $title, string $tofile, string|null $t1, string|null $t2): array|true
    {
        if (file_exists($tofile)) {
            $this->info("$tofile exists!");
            DB::connection('mysql')->table('tutorial')->insert([
                't0' => 'php',
                't1' => $t1,
                't2' => $t2,
                'title' => $title,
                'slug' => str_replace('.md', '', array_reverse(explode('/', $tofile))[0]),
                'mdc' => file_get_contents($tofile),
                'created_at' => pf_date_format(),
            ]);
            return true;
        }

        $this->line($title);

        $suppose = "Suppose you are a software engineer and your programming language is PHP. Your task is to write detailed Chinese tutorials based on my requirements. My first requirement is: [Please write a detailed tutorial titled \"$title\" that explains \"$title\" in depth. The tutorial should be in Chinese. The tutorial should be in Markdown format, with \"# $title\" as the first line of the Markdown.]";
        $suppose = <<<TXT
Suppose you are a software engineer and your preferred programming language is PHP. Your task is to write tutorials and technical documents according to my requirements. Here is my first requirement:

Please write a tutorial as detailed as possible, titled "$title", to explain "$title" in depth. The format of the tutorial needs to be markdown. The tutorial needs to be written in Chinese.
TXT;


        dump($suppose);

        $sfh = HttpClient::create();
        $sfh_resp = $sfh->request('POST', 'http://192.168.1.18:11434/api/chat', [
            // 'headers' => [],
            'timeout' => 900.0,
            'json' => [
                'model' => 'codestral:22b',
                // 'system' => "Let's say you're a computer science teacher at a university and your native language is Chinese.",
                'stream' => false,
                'keep_alive' => '15m',
                'options' => [
                    'num_ctx' => 2048 * 4,
                    'temperature' => 0.75,
                ],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $suppose,
                    ],
//                    [
//                        'role' => 'assistant',
//                        'content' => <<<TXT
//Absolutely, I'd be happy to help with that scenario! As a computer science teacher at a university, my primary
//focus would be on educating students about various aspects of computer science such as algorithms, data
//structures, artificial intelligence, machine learning, software engineering, and more.
//
//Given that Chinese is my native language, I would provide lectures in both English (to accommodate international
//students) and Chinese to ensure all students can understand the concepts being taught. For this reason, it's
//essential for me to have strong communication skills in both languages to effectively explain complex computer
//science concepts.
//
//I would also create study materials such as textbooks, slideshows, and practice problems in both English and
//Chinese. Additionally, I might utilize online resources, multimedia, and interactive learning platforms to enhance
//the learning experience. To support students' understanding, I could also organize office hours or virtual study
//groups where students can ask questions in either language.
//
//Lastly, being a teacher means staying updated with the latest research and technologies in computer science. This
//would involve reading academic papers, attending workshops and conferences, and collaborating with other
//professionals in the field. As my native language is Chinese, I might also have access to resources or discussions
//that occur within the Chinese-speaking community which could benefit both myself and my students.
//
//Overall, my role as a computer science teacher would be focused on providing a comprehensive and engaging learning
//experience for all of my students, regardless of their native language backgrounds.
//TXT,
//                    ],
//                    [
//                        'role' => 'user',
//                        'content' => "Please write a detailed tutorial titled \"$title\" that explains $title in depth. The tutorial should be in Chinese. The tutorial should be in Markdown format, with \"# $title\" as the first line of the Markdown.",
//                    ],
                ],
            ],
        ]);

        $sfh_arr = $sfh_resp->toArray();

        $this->line($sfh_arr['message']['content']);

        Assert::integer(file_put_contents($tofile, $sfh_arr['message']['content'] . "\n", LOCK_EX));

        return $sfh_arr;
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
