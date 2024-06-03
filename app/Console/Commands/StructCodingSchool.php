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
            'php',
//            'sql',
//            'python',
//            'java',
//            'c',
//            'cpp',
//            'cs',
        ];
        foreach ($pls as $pl) {
            if (!$this->isWindows && (time() - $this->ts > 500)) {
                break;
            }

            $plfile = "$this->dir/$pl.yaml";

            if (file_exists($plfile)) {
                goto trans;
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

            file_put_contents($plfile, Yaml::dump($tree, 5), LOCK_EX);

            trans:

            if ($this->option('trans')) {
                $plkv = Yaml::parseFile($plfile);

                foreach ($plkv as $kk => &$vv) {
                    if ($vv === null || $vv === '' || $this->option('trans') === 'force') {
                        $vv = (new AsciiSlugger())
                            ->slug($this->translate($kk, 'zh-CN', 'en'))
                            ->lower()
                            ->toString();
                    } elseif (is_array($vv)) {
                        foreach ($vv as $kkk => &$vvv) {
                            if ($vvv === null || $vvv === '' || $this->option('trans') === 'force') {
                                $vvv = (new AsciiSlugger())
                                    ->slug($this->translate($kkk, 'zh-CN', 'en'))
                                    ->lower()
                                    ->toString();
                            } elseif (is_array($vvv)) {
                                foreach ($vvv as $kkkk => &$vvvv) {
                                    if ($vvvv === null || $vvvv === '' || $this->option('trans') === 'force') {
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

                file_put_contents(
                    $plfile,
                    Yaml::dump($plkv, 5),
                    LOCK_EX
                );
            }

            if ($this->option('aigc')) {
                foreach (Yaml::parseFile($plfile) as $kk => $vv) {
                    $this->fs()->mkdir("$this->dir/$kk/");
                    foreach ($vv as $kkk => $vvv) {
                        Assert::true(
                            (is_string($vvv) && trim($vvv) !== '')
                            || (is_array($vvv) && count($vvv) !== 0)
                        );

                        if (is_string($vvv)) {
                            $this->aigc($vvv, "$this->dir/$kk/$vvv.md");
                        }

                        if (is_array($vvv)) {
                            $this->fs()->mkdir("$this->dir/$kk/$kkk/");
                            foreach ($vvv as $kkkk => $vvvv) {
                                $this->aigc($vvvv, "$this->dir/$kk/$kkk/$vvvv.md");
                            }
                        }
                    }
                }
            }
        }
    }

    private function aigc(string $title, string $tofile): array|true
    {
        if (file_exists($tofile)) {
            return true;
        }

        $sfh = HttpClient::create();
        $sfh_resp = $sfh->request('POST', 'http://192.168.1.18:11434/api/chat', [
            // 'headers' => [],
            'timeout' => 600.0,
            'json' => [
                'model' => 'codestral:22b',
                // 'system' => "Let's say you're a computer science teacher at a university and your native language is Chinese.",
                'stream' => false,
                'keep_alive' => '15m',
                'options' => [
                    'num_ctx' => 2048 * 8,
                ],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Let's say you're a computer science teacher at a university and your native language is Chinese.",
                    ],
                    [
                        'role' => 'assistant',
                        'content' => <<<TXT
Absolutely, I'd be happy to help with that scenario! As a computer science teacher at a university, my primary
focus would be on educating students about various aspects of computer science such as algorithms, data
structures, artificial intelligence, machine learning, software engineering, and more.

Given that Chinese is my native language, I would provide lectures in both English (to accommodate international
students) and Chinese to ensure all students can understand the concepts being taught. For this reason, it's
essential for me to have strong communication skills in both languages to effectively explain complex computer
science concepts.

I would also create study materials such as textbooks, slideshows, and practice problems in both English and
Chinese. Additionally, I might utilize online resources, multimedia, and interactive learning platforms to enhance
the learning experience. To support students' understanding, I could also organize office hours or virtual study
groups where students can ask questions in either language.

Lastly, being a teacher means staying updated with the latest research and technologies in computer science. This
would involve reading academic papers, attending workshops and conferences, and collaborating with other
professionals in the field. As my native language is Chinese, I might also have access to resources or discussions
that occur within the Chinese-speaking community which could benefit both myself and my students.

Overall, my role as a computer science teacher would be focused on providing a comprehensive and engaging learning
experience for all of my students, regardless of their native language backgrounds.
TXT,
                    ],
                    [
                        'role' => 'user',
                        'content' => "Please write a detailed tutorial titled \"$title\" that explains $title in depth. The tutorial should be in Chinese. The tutorial should be in Markdown format, with \"# $title\" as the first line of the Markdown.",
                    ],
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
