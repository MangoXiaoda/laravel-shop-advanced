<?php

namespace App\Console\Commands\Cron;

use App\Jobs\RefundCrowdfundingOrders;
use App\Models\CrowdfundingProduct;
use App\Models\Order;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FinishCrowdfunding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:finish-crowdfunding';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '结束众筹';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        CrowdfundingProduct::query()
            // 众筹结束时间小于当前时间
            ->where('end_at', '<=', Carbon::now())
            // 众筹状态为众筹中、
            ->where('status', CrowdfundingProduct::STATUS_PUNDING)
            ->get()
            ->each(function (CrowdfundingProduct $crowdfunding) {
                // 如果众筹目标金额大于实际目标金额
                if ($crowdfunding->target_amount > $crowdfunding->total_amount) {
                    // 调用众筹失败逻辑
                    $this->crowdfundingFailed($crowdfunding);
                } else {    
                    // 调用众筹成功逻辑
                    $this->crowdfundingSucceed($crowdfunding);
                }
            });
    }

    /**
     * 众筹成功逻辑
     */
    protected function crowdfundingSucceed(CrowdfundingProduct $crowdfunding)
    {   
        // 只需要将众筹状态改为众筹成功即可
        $crowdfunding->update([
            'status' => CrowdfundingProduct::STATUS_SUCCESS
        ]);
    }

    /**
     * 众筹失败逻辑
     */
    protected function crowdfundingFailed(CrowdfundingProduct $crowdfunding)
    {
        // 将众筹状态改为众筹失败
        $crowdfunding->update([
            'status' => CrowdfundingProduct::STATUS_FAIL
        ]);

        dispatch(new RefundCrowdfundingOrders($crowdfunding));
    }

}
