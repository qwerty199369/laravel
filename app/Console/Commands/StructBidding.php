<?php

namespace App\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use League\Csv\Writer;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
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

        dump(
            DB::table('bidding')
                ->where('vv', '{"code":"9499","data":{},"msg":"相关词长度1~20! ","redirectUrl":null}')
                ->delete()
        );

        $dict = json_decode_320(file_get_contents(__DIR__ . '/dict.json'));

        $words = ['心脏起搏器', '除颤仪', '透析设备', '麻醉机', '助听器', '腹腔镜', '喉镜', '胃镜', '结肠镜', '宫腔镜', '膀胱镜'];
        $noticeTypes = ['1091:11' => '中标']; // TODO: 暂未使用
        foreach ($words as $word) {
        foreach ($noticeTypes as $noticeType => $noticeTypeText) {
        foreach ($dict['data']['areaCodeTree'] as $code1) {
        foreach ($code1['children'] as $code2) {
            if (!$this->isWindows && (time() - $this->ts > 3000)) {
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
                        'User-Info' => 'uc_id=;uc_appid=585;acc_token=;acc_id=349117365;login_id=349117365:0;device_type=bid-pc718829581976469504;paas_appid=16;version=12;login_type=wx',
                        'Env' => 'WEB',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Api-Version' => '0',
                        'Client-Version' => '0',
                        'Auth-Type' => 'PAAS',
                        'X-Sourceid' => 'dc9492438d228d6c743ab3563ccf21f7',
                        'X-Timestamp' => time(),
                        'Priority' => 'u=1',
                        'Acs-Token' => trim(file_get_contents(__DIR__ . '/acs-token.txt')),
                        'Origin' => "https://{$this->option('domain')}",
                        'Connection' => 'keep-alive',
                        'Host' => "{$this->option('domain')}",
                        'Referer' => "https://{$this->option('domain')}/s?q=%E8%85%B9%E8%85%94%E9%95%9C&tab=0&count",
                        'User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0",
                        'Cookie' => trim(file_get_contents(__DIR__ . '/cookie.txt')),
                    ],
                    'bidding',
                    [
                        'kk' => "https://{$this->option('domain')}/crm/web/bid/xbb/bidding/search/api/search.$word.{$code1['value']}.{$code2['value']}.$page.json",
                        // 'kk' => "https://www.test.com/test".time().".json",
                    ],
                    fn() => false
                );

                if (!json_validate($searchResult)) {
                    break;
                }

                $searchResultArray = json_decode($searchResult, true);

                if (!isset($searchResultArray['data']['dataList'])) {
                    dd($searchResult);
                }

                foreach ($searchResultArray['data']['dataList'] as $datum) {
                    $datumId = $datum['id'];
                    // dump($datum);
                    $this->bidding[$datumId] = [
                        '标题' => $datum['title'],
                        '内容文本' => strip_tags($datum['content']),
                        '项目编号' => $datum['projectNo'],
                        '时间' => $datum['publishDate'],
                        '开标时间' => $datum['openBidingTime'],
                        '类型' => $datum['noticeType'],
                        '省份' => $datum['province'],
                        '城市' => $datum['city'],
                        '系统搜索城市' => $code2['title'],
                        '招标单位（如解析失败，请查看标题字段）' => implode(',', array_column($datum['tenderPrincipal'] ?? [], 'name')),
                        '招标单位类型' => implode(',', $datum['tenderPrincipalTypes'] ?? []),
                        '预算金额' => $datum['readableBudget'],
                        '系统解析预算金额（不可靠，以预算金额为准）' => $datum['budget'],
                        '代理单位' => implode(',', array_column($datum['agencyPrincipal'] ?? [], 'name')),
                        '相关产品标签' => implode(',', $datum['productLabels'] ?? []),
                        '中标单位（如解析失败，请查看内容文本字段）' => implode(',', array_column($datum['winnerPrincipal'] ?? [], 'name')),
                        '中标金额' => $datum['readableWinnerAmount'],
                        '系统解析数字金额（不可靠，以中标金额为准）' => $datum['winnerAmount'],
                        '标签' => implode(',', $datum['displayTags']),
                    ];
                }
            } while (count($searchResultArray['data']['dataList']) >= $pageSize);
        }
        }
        }
        }


        $csv = Writer::createFromString();
        $csv->setOutputBOM(Writer::BOM_UTF8);

        $i = 0;
        foreach ($this->bidding as $row) {
            $i++;
            if ($i === 1) {
                $csv->insertOne(array_keys($row));
            }
            $csv->insertOne(array_values($row));
        }

        $fs = new Filesystem();
        $fs->dumpFile(storage_path(sprintf(
            "/AQC__%s__%s.csv",
            implode('_', $noticeTypes),
            implode('_', $words)
        )), $csv->toString());
    }

    private array $bidding = [];
}
