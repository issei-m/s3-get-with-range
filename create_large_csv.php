<?php

$eol = "\r\n";
echo '"ID","メッセージ","投稿者名","メアド","日時"', $eol;

$message = str_replace(
    '"',
    '""',
    str_repeat('テスト！！', 100)
        . "\r\n" . str_repeat('`~!@#$%^&*()_+{}|:"[]\;\',./', 100)
        . "\r\n" . str_repeat('🤓👏🙏', 100)
);

for ($i = 0; $i < 10000; $i++) {
    $anyDateOf2018 = \date('Y-m-d H:i:s', mt_rand(1514732400, 1546268399));
    $rows = [$i + 1, $message, '田中 太郎', 'foo@example.com', $anyDateOf2018];
    echo '"' . implode('","', $rows) . '"', $eol;
}
