<?php

require 'vendor/autoload.php';

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


$minioConfig = [
    'version' => 'latest',
    'region' =>  $_ENV['MINIO_REGION'],
    'endpoint' => $_ENV['MINIO_ENDPOINT'],
    'credentials' => [
        'key' => $_ENV['MINIO_ACCESS_KEY'],
        'secret' => $_ENV['MINIO_SECRET_KEY'],
    ],
];

try {
    $s3Client = new S3Client($minioConfig);
    $bucketName = $_ENV['MINIO_BUCKET'];

    $result = $s3Client->listObjectsV2([
        'Bucket' => $bucketName,
    ]);

    if (!isset($result['Contents'])) {
        echo "No objects found in the bucket.\n";
        exit;
    }

    $folders = [];
    foreach ($result['Contents'] as $object) {
        $key = $object['Key'];
        $lastModified = $object['LastModified'];

        $folder = substr($key, 0, strrpos($key, '/') + 1);
        $folders[$folder][] = [
            'Key' => $key,
            'LastModified' => $lastModified,
        ];
    }

    foreach ($folders as $folder => $files) {
        usort($files, function ($a, $b) {
            return strtotime($b['LastModified']) <=> strtotime($a['LastModified']);
        });

        $filesToKeep = array_slice($files, 0, 2);
        $filesToDelete = array_slice($files, 2);
        echo "Deleting files in folder '$folder': \n \n";
        foreach ($filesToDelete as $file) {
            try {
                $s3Client->deleteObject([
                    'Bucket' => $bucketName,
                    'Key' => $file['Key'],
                ]);
                echo "Deleted: " . $file['Key'] . "\n";
            } catch (AwsException $e) {
                echo "Error deleting " . $file['Key'] . ": " . $e->getMessage() . "\n";
            }
        }
        if(!count($filesToDelete)) {
            echo "No files to delete.\n";
        }

        echo " \n Kept last two files in folder '$folder': \n";
        foreach ($filesToKeep as $file) {
            echo "- " . $file['Key'] . "\n";
        }
        echo "\n";
    }
} catch (AwsException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
