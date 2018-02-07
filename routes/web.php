<?php

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/**
 * Twilioから呼ばれるWebHook
 */
Route::post('/recieveFax', function () {

    $twiml = <<< EOM
<?xml version="1.0" encoding="UTF-8"?>
<Response>
<Receive action="/actionRecieveFax" method="POST" />
</Response>
EOM;

    return response($twiml, 200)
        ->header('Content-Type', 'text/xml');

});

/**
 * WebHookで指定したAction
 */
Route::post('/actionRecieveFax', function () {

    // DEBUG
    Log::debug(Input::all());

    if (Input::get('FaxStatus') == 'received') {
        // 発信元
        $from = Input::get('From');
        // メディアURL
        $from = Input::get('MediaUrl');

        // 一時ファイル名を生成
        $tmpFilename = tempnam(sys_get_temp_dir(), 'TwilioFax_');

        Log::debug($tmpFilename);

        // メディアURLのファイルをダウンロードし、一時ファイルに格納
        $ch = curl_init($from);
        $fp = fopen($tmpFilename, 'wb');

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // SSLで相手先の検証をしない
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // リダイレクトを許可する
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        try {
            if (!curl_exec($ch)) {
                throw new ErrorException(curl_error());
            }
        } catch (ErrorException $e) {
            var_dump($e);
        }

        curl_close($ch);
        fclose($fp);
    }

    return response('OK');

});

/**
 * FAXを送信するURL
 */
Route::get('/sendFax', function () {

    $client = new \GuzzleHttp\Client([
        'auth' => [
            env('TWILIO_SID'), env('TWILIO_TOKEN')
        ],
        'base_uri' => 'https://fax.twilio.com',
    ]);

    $mediaUrl = url(Storage::url('test.pdf'));

    // DEBUG
    Log::debug(Input::all());

    $res = $client->request(
        'POST',
        '/v1/Faxes',
        [
            'form_params' => [
                'From' => env('TWILIO_FROM'),
                'To' => '+' . Input::get('To'),
                'MediaUrl' => $mediaUrl,
            ]
        ]
    );

    // DEBUG
    Log::debug(var_dump($res));

    return response('OK');

});