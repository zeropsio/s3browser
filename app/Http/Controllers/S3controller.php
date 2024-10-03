<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;

class S3Controller extends Controller
{
    /**
     * Display the contents of an S3 bucket and connection information with pagination.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $sortBy = $request->get('sortBy', 'created_at');
        $sortOrder = $request->get('sortOrder', 'desc');
        $maxKeys = 1000;
        $perPage = 24;
        $page = $request->get('page', 1);
        $continuationToken = $request->get('continuationToken', null);


        $s3Client = new S3Client([
            'region' => config('filesystems.disks.s3.region'),
            'version' => 'latest',
            'endpoint' => config('filesystems.disks.s3.endpoint'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $bucket = config('filesystems.disks.s3.bucket');

        $allFiles = collect();
        $params = [
            'Bucket' => $bucket,
            'MaxKeys' => $maxKeys,
        ];

        if ($continuationToken) {
            $params['ContinuationToken'] = $continuationToken;
        }

        $result = $s3Client->listObjectsV2($params);

        $files = collect($result['Contents'])->map(function ($file) {
            return [
                'path' => $file['Key'],
                'lastModified' => $file['LastModified'],
                'ts' => $file['LastModified']->getTimestamp(),
                'size' => $file['Size'],
            ];
        });

        $allFiles = $allFiles->merge($files);

        $nextContinuationToken = $result['IsTruncated'] ? $result['NextContinuationToken'] : null;

        if ($sortBy === 'name') {
            $allFiles = $allFiles->sortBy('path', SORT_REGULAR, $sortOrder === 'desc');
        } elseif ($sortBy === 'created_at') {
            $allFiles = $allFiles->sortBy('ts', SORT_REGULAR, $sortOrder === 'desc');
        } elseif ($sortBy === 'size') {
            $allFiles = $allFiles->sortBy('size', SORT_REGULAR, $sortOrder === 'desc');
        }

        $paginatedFiles = new LengthAwarePaginator(
            $allFiles->forPage($page, $perPage),
            $allFiles->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        );

        $totalFileCount = $this->getTotalFileCount($s3Client, $bucket);

        // TODO: print out for debug info in view
        $connectionInfo = [
            'key' => config('filesystems.disks.s3.key'),
            'bucket_name' => config('filesystems.disks.s3.bucket'),
            'region' => config('filesystems.disks.s3.region'),
            'endpoint' => config('filesystems.disks.s3.endpoint'),
        ];


        return view('index', compact('paginatedFiles', 'connectionInfo', 'nextContinuationToken', 'totalFileCount'));
    }


    /**
     * Delete a file from the S3 bucket.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFile(Request $request)
    {
        $filePath = $request->input('filePath');

        Storage::disk('s3')->delete($filePath);

        return response()->json([
            'message' => 'File deleted successfully',
            'file_path' => $filePath,
        ]);
    }

    /**
     * Upload a test file to the S3 bucket.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadTestFile()
    {

        $testContent = "This is a test file uploaded on " . now();
        $fileName = 'test-file-' . time() . '.txt';

        Storage::disk('s3')->put($fileName, $testContent);

        return response()->json([
            'message' => 'Test file uploaded successfully',
            'file_name' => $fileName,
        ]);
    }


    /**
     * Fetch the total number of files in the S3 bucket.
     *
     * @param S3Client $s3Client
     * @param string $bucket
     * @return int
     */
    private function getTotalFileCount(S3Client $s3Client, $bucket)
    {
        $totalCount = 0;
        $continuationToken = null;

        do {
            $params = [
                'Bucket' => $bucket,
                'MaxKeys' => 1000,
            ];

            if ($continuationToken) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result = $s3Client->listObjectsV2($params);
            $totalCount += isset($result['KeyCount']) ? $result['KeyCount'] : 0;
            $continuationToken = isset($result['NextContinuationToken']) ? $result['NextContinuationToken'] : null;

        } while ($continuationToken);

        return $totalCount;
    }

    /**
     * Check if a file is an image based on its extension.
     *
     * @param array $file
     * @return bool
     */
    public function isImage($file)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
        $extension = pathinfo($file['path'], PATHINFO_EXTENSION);

        return in_array(strtolower($extension), $imageExtensions);
    }

    public function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $size = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $size = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $size = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $size = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $size = $bytes . ' byte';
        } else {
            $size = '0 bytes';
        }

        return $size;
    }

}
