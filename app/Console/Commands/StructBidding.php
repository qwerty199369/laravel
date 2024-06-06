<?php

namespace App\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\DomCrawler\Crawler;
use Webmozart\Assert\Assert;

class StructBidding extends BaseBot
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'struct:bidding {--domain=} {--sleep=}';

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

        if (!Schema::hasTable('bidding')) {
            Schema::create('bidding', function (Blueprint $table) {
                $table->string('kk')->unique()->primary();
                $table->text('vv');
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
            });
        }

        $dict = json_decode_320(file_get_contents(__DIR__ . '/dict.json'));

        $words = ['腹腔镜', '喉镜', '胃镜', '结肠镜', '宫腔镜', '膀胱镜'];
        foreach ($words as $word) {
        foreach ($dict['data']['areaCodeTree'] as $code1) {
        foreach ($code1['children'] as $code2) {
            if (time() - $this->ts > 3000) {
                break;
            }

            $page = 0;
            $pageSize = 50;
            do {
                $page++;

                // 1091:11 中标
            $postJson = <<<JSON
{
    "query": {
        "endTime": "",
        "keyword": "$word",
        "noticeTypes": [
            "1091:11"
        ],
        "startTime": "",
        "enterpriseId": "",
        "enterpriseName": "",
        "searchSource": 1,
        "matchType": "term",
        "matchFields": [
            "title",
            "content"
        ],
        "informationTypes": [
            "中标"
        ],
        "sortType": "desc",
        "excludeKeyword": "",
        "areaQuery": {
            "dicts": [
                {
                    "code": "{$code1['value']}",
                    "children": [
                        {
                            "code": "{$code2['value']}",
                            "children": []
                        }
                    ]
                }
            ]
        },
        "tenderPrincipalTypeCodes": [],
        "tenderItemCategories": [],
        "tenderItemIndustries": [],
        "enterpriseType": [],
        "contactFilterType": "",
        "agencyPrincipalFilterType": "",
        "winnerFilterType": "",
        "attachmentFilterType": "",
        "biddingAcquireTimeItem": "",
        "tenderTimeItem": "",
        "openBidingTimeItem": "",
        "pageNum": {$page},
        "pageSize": {$pageSize},
        "platform": "pc"
    }
}
JSON;

                $searchResult = $this->postURLWithDB(
                    "https://{$this->option('domain')}/crm/web/bid/xbb/bidding/search/api/search",
                    trim($postJson),
                    [
                        'Accept' => 'application/json, text/plain, */*',
                        'Accept-Encoding' => 'gzip, deflate, br, zstd',
                        'Accept-Language' => 'zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2',
                        'Content-Type' => 'application/json;charset=UTF-8',
                        'User-Info' => 'uc_id=;uc_appid=585;acc_token=61219e2bba313627873b5e6babf55dac58a5ba2d1ebbeaf4e63dde3bfe7dc5a9;acc_id=311101137;login_id=311101137:0;device_type=bid-pc;paas_appid=16;version=12;login_type=passport',
                        'Env' => 'WEB',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Api-Version' => '0',
                        'Client-Version' => '0',
                        'Auth-Type' => 'PAAS',
                        'X-Sourceid' => '1003ef8b0ca38677023de877ca6680aa',
                        'X-Timestamp' => '1717649520',
                        'Acs-Token' => trim(file_get_contents(__DIR__ . '/acs-token.txt')),
                        'Origin' => "https://{$this->option('domain')}",
                        'Connection' => 'keep-alive',
                        'Host' => "{$this->option('domain')}",
                        'Referer' => "https://{$this->option('domain')}/s?q=%E8%85%B9%E8%85%94%E9%95%9C&tab=0&count",
                        'User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 Edg/125.0.0.0",
                        'Cookie' => trim(file_get_contents(__DIR__ . '/cookie.txt')),
                    ],
                    'bidding',
                    [
                        'kk' => "https://{$this->option('domain')}/crm/web/bid/xbb/bidding/search/api/search.$word.$page.json",
                        // 'kk' => "https://www.test.com/test".time().".json",
                    ],
                    fn() => true
                );

                if (!json_validate($searchResult)) {
                    break;
                }

                $searchResult = json_decode($searchResult, true);

                dd($searchResult);

                if (!isset($searchResult['data']['rows'])) {
                    break;
                }

                foreach ($searchResult['data']['rows'] as $row) {
                    $productHtml = $this->getURLWithDB(
                        "https://www.{$this->option('domain')}/prod/detail/{$row['id']}.html#{$row['id']}",
                        [
                            'Accept' => '*/*',
                            'Accept-Encoding' => 'gzip, deflate',
                            'Host' => "www.{$this->option('domain')}",
                            'User-Agent' => "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)",
                        ],
                        'bidding',
                        [
                            'kk' => "https://www.{$this->option('domain')}/prod/detail/{$row['id']}.html",
                        ]
                    );
                }
            } while (count($searchResult['data']['rows']) >= $pageSize);
        }
        }
        }
    }
}
