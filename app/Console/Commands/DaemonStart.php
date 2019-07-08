<?php

namespace App\Console\Commands;

use App\Webapp;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Event\BridgeEvent;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Event\ExtensionStatusEvent;
use PAMI\Message\Event\HangupEvent;
use PAMI\Message\Event\NewstateEvent;
use PAMI\Message\Event\UserEventEvent;

class DaemonStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daemon:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start daemon and recieve events.';

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
        $options = [
            'host' => '10.0.0.54',
            'scheme' => 'tcp://',
            'port' => 5038,
            'username' => 'vtiger',
            'secret' => 'vtiger123',
            'connect_timeout' => 10000,
            'read_timeout' => 10000
        ];

        $url = 'http://server/anicrm/modules/PBXManager/callbacks/PBXManager.php';

        $client = new ClientImpl($options);

        $this->info('Press Ctrl + C to exit.');
        $this->info('Starting event listener...');

        $client->open();

        $client->registerEventListener(function (EventMessage $message) use ($url) {
            if($message instanceof BridgeEvent && $message->getKey('bridgestate') == 'Link') {
                if(Str::contains($message->getKey('channel1'), 'TCL')) {
                    // This is incoming
                    $this->info($message->serialize());
                    $from_number = Str::startsWith($message->getKey('callerid1'), "0") && Str::length($message->getKey('callerid1') == 10)
                        ? $message->getKey('callerid1') : "0" . $message->getKey('callerid1');
                    $record = Webapp::create([
                        'uid' => Str::uuid(),
                        'uniqueid1' => $message->getKey('uniqueid1'),
                        'uniqueid2' => $message->getKey('uniqueid2'),
                        'channel1' => $message->getKey('channel1'),
                        'channel2' => $message->getKey('channel2'),
                        'event' => $message->getKey('event'),
                        'direction' => 'Incoming',
                        'from_number' => $from_number,
                        'to_number' => $message->getKey('callerid2'),
                        'starttime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'bridged' => true,
                        'state' => $message->getKey('bridgestate'),
                    ]);

                    $httpclient = new Client();
                    $_url = "";
                    $params = [
                        /*'query' => [
                            'vtigersignature' => 'abdullah',
                            'callstatus' => $record->direction,
                            'callerIdNumber' => $record->to_number,
                            'customerNumber' => $record->from_number,
                            'SourceUUID' => $record->uid
                        ],*/
                        'on_stats' => function (TransferStats $stats) use (&$_url) {
                            $_url = $stats->getEffectiveUri();
                        }
                    ];
                    $response = $httpclient->get($url."?vtigersignature=abdullah&callstatus=$record->direction&callerIdNumber=$record->to_number&customerNumber=$record->from_number&SourceUUID=$record->uid", $params);

                    $this->info(dump((string)$response->getBody()));
                    $this->info(dump($_url));

                } elseif(Str::contains($message->getKey('channel2'), 'TCL')) {
                    $this->info($message->serialize());
                    $record = Webapp::create([
                        'uid' => Str::uuid(),
                        'uniqueid1' => $message->getKey('uniqueid1'),
                        'uniqueid2' => $message->getKey('uniqueid2'),
                        'channel1' => $message->getKey('channel1'),
                        'channel2' => $message->getKey('channel2'),
                        'event' => $message->getKey('event'),
                        'direction' => 'outbound',
                        'from_number' => $message->getKey('callerid1'),
                        'to_number' => $message->getKey('callerid2'),
                        'starttime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'bridged' => true,
                        'state' => $message->getKey('bridgestate'),
                    ]);
                }
            } elseif ($message instanceof HangupEvent) {
                $this->info($message->serialize());
                $record = Webapp::where([
                    ['channel2', $message->getKey('channel')],
                    ['uniqueid2', $message->getKey('uniqueid')]
                ])->first();
                //$this->info(serialize($record));
                if(!is_null($record)) {
                    $endtime = Carbon::now();
                    $record->endtime = $endtime->format('Y-m-d H:i:s');
                    $record->totalduration = $endtime->diffInSeconds(Carbon::parse($record->starttime));
                    $record->callcause = $message->getKey('cause-txt');
                    $record->save();

                    $httpclient = new Client();
                    $response = $httpclient->get($url."?vtigersignature=abdullah&callstatus=Hangup&callUUID=$record->uid&causetxt=$record->callcause&HangupCause=$record->callcause&EndTime=$record->endtime&Duration=$record->totalduration");
                }
            }
        });

        while(true) {
            $client->process();
            usleep(1000);
        }

        $client->process();
        return 0;
    }
}
