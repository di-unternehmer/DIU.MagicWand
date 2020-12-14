'use strict';
const loadConfiguration = require('./configuration').loadConfiguration
const aws = require('aws-sdk')
const s3 = new aws.S3({ region: 'eu-central-1' })
const environment = '/DatabaseBackup'
const mysqldump = require("mysqldump");
const stream = require('stream')
let configuration

var fs = require('fs');
var path = require('path');

async function uploadFile(filePath)
{
    const readStream = fs.createReadStream(filePath);
    const writeStream = new stream.PassThrough();
    readStream.pipe(writeStream);

    var fname = path.basename(filePath);
    var params = {
        Bucket : configuration.bucket,
        Key : fname,
        Body : writeStream
    }
    console.log('bucket', params.Bucket)
    console.log('key', params.Key)

    let uploadPromise = new Promise((resolve, reject) => {
        s3.upload(params, (err, data) => {
            if (err) {
                reject(err);
            } else {
                resolve(data);
            }
        });
    });

    var res = await uploadPromise;
    return res;
}

module.exports.handler = async (event, context, callback) => {

    console.log('Load Configuration')
    configuration = await loadConfiguration(aws, environment)
    console.log('Create SQL dump')
    var backupName = new Date().toISOString()+'-rds-backup.sql.gz'

    await mysqldump({
        connection: {
            host: configuration.host,
            user: configuration.user,
            password: configuration.password,
            database: configuration.name
        },
        dumpToFile: '/tmp/' + backupName,
        compressFile: true
    });

    console.log('Save SQL dump to S3 bucket')
    var res2 = await uploadFile('/tmp/'+ backupName);
    console.log('upload result', res2);

    return {
        "Bucket": configuration.bucket,
        "Key": backupName
    }
};
