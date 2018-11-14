# s3-get-with-range-php

S3のGETリクエストで `Content-Range` ヘッダーの検証を行います。

## 下準備

1. `composer install` でdependenciesを解決します
2. 次にテスト用のCSVを作ります。以下を実行して下さい:

  ```
  $ php create_large_csv.php > ./awesome.csv
  ```

3. このコードでは実際のAmazon S3は使っておらず、[minio](https://github.com/minio/minio)を使っているので起動します:

  ```
  $ docker run --rm -e MINIO_ACCESS_KEY=MINIO4DEV -e MINIO_SECRET_KEY=MINIO4DEV -p 9000:9000 minio/minio server /data
  ```

  (オプションは各自に任せます)

4. 続いてAWS CLIでバケットを作り、先程のS3をアップロードします:

  ```
  $ export AWS_ACCESS_KEY_ID=MINIO4DEV; export AWS_SECRET_ACCESS_KEY=MINIO4DEV
  {
      "Location": "/test"
  }

  $ aws --endpoint-url http://127.0.0.1:9000 s3api put-object --bucket test --key awesome.csv --body ./awesome.csv
  {
      "ETag": "\"965b5c82c2541da6ee5112c640aaed31\""
  }
  ```

これで準備完了です。

## テスト

以下のコマンドで実行:

```
$ php index.php
.
.
.
9998, 2018-10-09 18:48:20 / 12.0000 MB
9999, 2018-03-09 07:39:25 / 12.0000 MB
10000, 2018-07-19 01:12:00 / 12.0000 MB
```

出力内容は各行の最初と最後のセルの値と、現在のメモリ使用量です。
見ての通り、CSVファイル自体が大きくてもメモリ使用量が抑えられています。****
