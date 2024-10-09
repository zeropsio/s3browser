# Zerops S3browser for Development

This project is a simple Laravel-based browser for viewing and managing files stored on an S3 bucket. It is designed for development purposes, providing an interface for listing, uploading, and deleting files.

![s3browser](https://github.com/zeropsio/recipe-shared-assets/blob/main/covers/svg/cover-s3browser.svg)

## How to Add to Your Existing Project

This tool connects to Zerops Object Storage using the hostname `storage` by default. Zerops handles the credentials automatically, so if your S3 storage is set to `storage`, no further configuration is needed.

To add it to an existing project, go to the service details, click "Import service," and add the following code:

```yaml
#yamlPreprocessor=on
services:
  - hostname: s3browser
    type: php-nginx@8.3
    buildFromGit: https://github.com/zeropsio/s3browser
    enableSubdomainAccess: true
    envSecrets:
      APP_KEY: <@generateRandomString(<32>)>
```

Connection to Storage service with different hostname is in progress. 

## Features

- **File Listing**: Browse all files in the S3 bucket with pagination.
- **File Sorting**: Sort files by name, creation date, or size.
- **File Deletion**: Delete individual files from the S3 bucket.
- **Test File Upload**: Upload a sample test file to the bucket.
- **Total File Count**: Displays the total number of files in the bucket.

