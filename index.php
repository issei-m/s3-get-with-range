<?php

require __DIR__ . '/vendor/autoload.php';

$s3 = new \Aws\S3\S3Client([
    'version' => '2006-03-01',
    'region' => 'us-west-2',
    'endpoint' => 'http://127.0.0.1:19000',
    'use_path_style_endpoint' => true,
    'access_key_id' => 'MINIO4DEV',
    'secret_access_key' => 'MINIO4DEV',
]);

$objectAddress = ['Bucket' => 'test', 'Key' => 'awesome.csv'];

$contentSize = $s3->headObject($objectAddress)['ContentLength'];

$csvContentChunkStreams = \DusanKasan\Knapsack\Collection
    ::from(function () use ($contentSize) {
        static $chunkSize = 1024 * 1024;

        // オブジェクトのサイズから1MB毎のチャンクのリストを作る
        for ($start = 0, $end = 0; $contentSize > $end; $start = $end + 1) {
            $end = \min($start + $chunkSize, $contentSize);
            yield [$start, $end];
        }
    })
    ->map(function (array $range) {
        \assert(2 === \count($range));

        return \sprintf('bytes=%s-%s', $range[0], $range[1]);
    })
    ->map(function (string $rfc2616ContentRange) use ($s3, $objectAddress) {
        return $s3->getObject(array_merge($objectAddress, ['Range' => $rfc2616ContentRange]))['Body'];
    })
;

$csvRows = \DusanKasan\Knapsack\Collection::from(function () use ($csvContentChunkStreams) {
    static $expectedCsvColumns = 12;

    $incompleteContentBuffer = '';
    $counter = 0;

    foreach ($csvContentChunkStreams as $stream) {
        \assert($stream instanceof \Psr\Http\Message\StreamInterface);

        $fp = \fopen('php://memory', 'r+');

        try {
            \fwrite($fp, $incompleteContentBuffer . $stream->__toString());
            \fseek($fp, 0);

            $readAt = 0;
            $completeRows = [];
            $numReadRows = 0;

            while ($csvRow = \fgetcsv($fp)) {
                $numReadRows++;

                // CSVの列数が想定通りの列数であれば$completeRowsに行を貯めておく
                if ($expectedCsvColumns === \count($csvRow)) {
                    $completeRows[] = $csvRow;
                    $readAt = \ftell($fp);
                }
            }

            // 先のwhileは最低でも2回以上ループしている必要がある.
            // 何故なら列数が想定通りであっても行の途中である可能性がある為である.
            if ($numReadRows < 2) {
                fseek($fp, 0);
                $incompleteContentBuffer = stream_get_contents($fp);

                continue;
            } else {
                foreach ($completeRows as $v) {
                    yield $counter++ => $v;
                }

                // 残りのコンテンツは行として不完全であるとみなし、次のチャンクと連結する為にバッファリングしておく.
                $currentPos = \ftell($fp);
                if ($currentPos - $readAt > 0) {
                    \fseek($fp, $readAt);
                    $incompleteContentBuffer = \fread($fp, $currentPos - $readAt);
                }
            }
        } finally {
            \fclose($fp);
        }
    }
});

foreach ($csvRows as $v) {
    echo sprintf('%s / %.4f MB', print_r($v, true), memory_get_peak_usage() / 1024 / 1024), "\n";
}
