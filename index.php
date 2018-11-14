<?php

require __DIR__ . '/vendor/autoload.php';

$s3 = new \Aws\S3\S3Client([
    'version' => '2006-03-01',
    'region' => 'us-west-2',
    // MINIO
    'endpoint' => 'http://127.0.0.1:9000',
    'use_path_style_endpoint' => true,
    'credentials' =>  new Aws\Credentials\Credentials('MINIO4DEV', 'MINIO4DEV'),
]);

$csvObjectInfo = ['Bucket' => 'test', 'Key' => 'awesome.csv'];

$csvContentLength = $s3->headObject($csvObjectInfo)['ContentLength'];

// CSVの内容を1MB毎に分割したリストを作成.
$csvContentChunks = \DusanKasan\Knapsack\Collection
    ::from(function () use ($csvContentLength) {
        static $chunkSize = 1024 * 1024;

        // オブジェクトのサイズから1MB毎のチャンクのリストを作る
        for ($start = 0, $end = 0; $csvContentLength > $end; $start = $end + 1) {
            $end = \min($start + $chunkSize, $csvContentLength);
            yield [$start, $end];
        }
    })
    ->map(function (array $range) {
        \assert(2 === \count($range));

        return \sprintf('bytes=%s-%s', $range[0], $range[1]);
    })
    ->map(function (string $rfc2616ContentRange) use ($s3, $csvObjectInfo) {
        return (string) $s3->getObject(\array_merge($csvObjectInfo, ['Range' => $rfc2616ContentRange]))['Body'];
    })
;

$csvRows = \DusanKasan\Knapsack\Collection::from(function () use ($csvContentChunks) {
    static $expectedCsvColumns = 5;

    $incompleteContentBuffer = '';
    $counter = 0;

    foreach ($csvContentChunks as $chunk) {
        $fp = \fopen('php://memory', 'r+');

        try {
            \fwrite($fp, $incompleteContentBuffer . $chunk);
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
            // 何故なら例え列数が想定通りであっても、1回目のループで取得された行は途中で途切れている可能性がある為.
            if ($numReadRows < 2) {
                fseek($fp, 0);
                $incompleteContentBuffer = \stream_get_contents($fp);

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
    echo \sprintf('%s, %s / %.4f MB', $v[0], $v[4], \memory_get_usage(true) / 1024 / 1024), "\n";
}
