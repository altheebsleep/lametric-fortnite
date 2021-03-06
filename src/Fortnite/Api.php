<?php

namespace Fortnite;

use Fortnite\Exception\ConfigException;
use Fortnite\Exception\InternalErrorException;
use Fortnite\Exception\MissingParameterException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Api
{
    const PLATFORMS = [
        'PC'    => 'pc',
        'Xbox1' => 'xbl',
        'PS4'   => 'psn',
    ];

    const MODS = [
        'solo'  => 'p2',
        'duo'   => 'p10',
        'squad' => 'p9',
    ];

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * Api constructor.
     * @param Validator $validator
     * @throws ConfigException
     */
    public function __construct(Validator $validator)
    {
        if (!is_file(__DIR__ . '/../../config/parameters.php')) {
            throw new ConfigException('Internal error: missing parameter file');
        } else {
            $this->parameters = require __DIR__ . '/../../config/parameters.php';
        }

        $this->validator = $validator;
    }

    /**
     * @return array
     * @throws InternalErrorException
     * @throws MissingParameterException
     */
    public function fetchData()
    {
        $platform = self::PLATFORMS[$this->validator->getParameters()['platform']];

        try {

            $endpoint = 'https://api.fortnitetracker.com/v1/profile/' .
                $platform . '/' .
                $this->validator->getParameters()['player'];

            $client = new Client();
            $res    = $client->request('GET', $endpoint, [
                'headers' => [
                    'TRN-Api-Key' => $this->parameters['api-key'],
                ],
                'proxy'   => $this->parameters['proxies']
            ]);

            $json = (string)$res->getBody();
            $data = json_decode($json);

        } catch (\Exception $e) {
            throw new MissingParameterException($e->getMessage());
        } catch (GuzzleException $e) {
            throw new InternalErrorException('Internal error');
        }

        return $this->formatData($data);
    }

    /**
     * @param $data
     * @return array
     */
    private function formatData($data)
    {
        $dataToReturn = [
            'name'           => $data->epicUserHandle,
            'wins'           => 0,
            'kills'          => 0,
            'matches_played' => 0,
        ];

        $modsToFetch = [];

        foreach (self::MODS as $mod => $apiMod) {
            // Lametric switch values (true / false)...
            if ($this->validator->getParameters()['include' . ucwords($mod)] === 'true') {
                $modsToFetch[] = $apiMod;
            }
        }

        foreach (self::MODS as $mod => $apiMod) {
            // Lametric switch values (true / false)...
            if ($this->validator->getParameters()['include' . ucwords($mod)] === 'true') {
                $modsToFetch[$mod] = $apiMod;
            }
        }

        foreach ($modsToFetch as $mod => $apiMod) {
            if ($this->validator->getParameters()['include' . ucwords($mod)] && count($data->stats->{$apiMod})) {
                $dataToReturn['wins']           += $data->stats->{$apiMod}->top1->valueInt;
                $dataToReturn['kills']          += $data->stats->{$apiMod}->kills->valueInt;
                $dataToReturn['matches_played'] += $data->stats->{$apiMod}->matches->valueInt;
            }
        }

        // set values
        $dataToReturn['wins']    .= ' WINS';
        $dataToReturn['kd']      = round(
                $dataToReturn['kills'] /
                ($dataToReturn['matches_played'] - $dataToReturn['wins']),
                2
            ) . ' K/D';
        $dataToReturn['winrate'] = round(($dataToReturn['wins'] / $dataToReturn['matches_played']) * 100, 2) . '%';

        return $dataToReturn;
    }
}
