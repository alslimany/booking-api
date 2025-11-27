<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
#
use Codewithkyrian\Transformers\Transformers;
use Illuminate\Support\Facades\Http;
use function Codewithkyrian\Transformers\Pipelines\pipeline;
#
class TranslateTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $text;
    private $lang;
    /**
     * Create a new job instance.
     */
    public function __construct($text, $lang = "ar")
    {
        $this->text = $text;
        $this->lang = $lang;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $key = $this->lang . "_" . md5($this->text);

        if (!cache()->has($key)) {
            Transformers::setup()->setCacheDir(cacheDir: "/www/wwwroot/booking-api.atom.ly/.transformers-cache")->apply();
            $translationPipeline = pipeline("translation", 'Xenova/nllb-200-distilled-600M');


            $output = $translationPipeline(
                $this->text,
                maxNewTokens: 256,
                tgtLang: 'arb_Arab'
            );


            cache()->put($key, $output[0]["translation_text"], now()->addDays(1));

            // $response = Http::post('https://libretranslate.com/translate', [
            //     "q" => "$this->text",
            //     "source" => "auto",
            //     "target" => "$this->lang",
            //     "format" => "text",
            //     "alternatives" => 3,
            //     "api_key" => ""
            // ]);
            // cache()->put($key, $response->json('translatedText'), now()->addDays(1));
        }
    }
}
