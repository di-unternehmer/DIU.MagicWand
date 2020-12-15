<?php

namespace DIU\MagicWand\Service;

use Neos\Flow\Annotations as Flow;
use Aws\Lambda\LambdaClient;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\InflateStream;

class LambdaService
{

    /**
     * @Flow\InjectConfiguration(package="DIU.MagicWand", path="aws")
     * @var array
     */
    protected $settings;

    public function getLambdaContent(?string $profile) : string
    {
        $config = [
            'profile' => $profile ?? 'default',
            'version' => 'latest',
            'region'  => $this->settings['region'],
            'http'    => [
                'timeout' => 0,
                'connect_timeout' => 0
            ]
        ];

        $result = $this->executeLambda($config);
        $file = $this->fetchDumpFromS3($result, $config);
        return $this->writeFileToData($file);
    }

    /**
     * @param \Aws\Result $result
     * @param array $config
     * @return \Aws\Result
     */
    private function fetchDumpFromS3(\Aws\Result $result, array $config): \Aws\Result
    {
        $fileToDownload = json_decode((string)$result->get('Payload'), true);
        $s3 = new S3Client($config);
        $file = $s3->getObject($fileToDownload);
        return $file;
    }

    /**
     * @param \Aws\Result $file
     */
    private function writeFileToData(\Aws\Result $file): string
    {
        $filename = FLOW_PATH_DATA . 'dump.sql';
        $inflatedBody = new InflateStream($file['Body']); // This is now readable
        $file = fopen($filename, 'a');
        fwrite($file, (string)$inflatedBody);
        fclose($file);
        return $filename;
    }

    /**
     * @param array $config
     * @return \Aws\Result
     */
    private function executeLambda(array $config): \Aws\Result
    {
        $client = new LambdaClient($config);
        $result = $client->invoke([
            'FunctionName' => $this->settings['functionName'],
            'Timeout' => 0
        ]);
        return $result;
    }

}
