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
        $sortBy = $request->get('sortBy', 'name');
        $sortOrder = $request->get('sortOrder', 'desc');
        $maxKeys = 1000; // Max entries per S3 request
        $perPage = 24; // Entries per page for the UI
        $page = $request->get('page', 1); // Laravel pagination current page
        $continuationToken = $request->get('continuationToken', null); // S3 continuation token

        // Initialize the S3 client
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

        // List all objects in the S3 bucket using the ContinuationToken for pagination
        $allFiles = collect();
        $params = [
            'Bucket' => $bucket,
            'MaxKeys' => $maxKeys,
        ];

        // Use the continuation token if provided
        if ($continuationToken) {
            $params['ContinuationToken'] = $continuationToken;
        }

        // Fetch files from S3
        $result = $s3Client->listObjectsV2($params);
//        dd($result);
        // Collect the files and store them
        $files = collect($result['Contents'])->map(function ($file) {
            return [
                'path' => $file['Key'],
                'lastModified' => $file['LastModified'],
                'ts' => $file['LastModified']->getTimestamp(),
                'size' => $file['Size'],
            ];
        });

        $allFiles = $allFiles->merge($files);

        // Check if more files exist
        $nextContinuationToken = $result['IsTruncated'] ? $result['NextContinuationToken'] : null;

        // Sort the files
        if ($sortBy === 'name') {
            $allFiles = $allFiles->sortBy('path', SORT_REGULAR, $sortOrder === 'desc');
        } elseif ($sortBy === 'created_at') {
            $allFiles = $allFiles->sortBy('ts', SORT_REGULAR, $sortOrder === 'desc');
        } elseif ($sortBy === 'size') {
            $allFiles = $allFiles->sortBy('size', SORT_REGULAR, $sortOrder === 'desc');
        }


        // Handle pagination for the UI
        $paginatedFiles = new LengthAwarePaginator(
            $allFiles->forPage($page, $perPage),
            $allFiles->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        );

        // Get the total number of files in the S3 bucket
        $totalFileCount = $this->getTotalFileCount($s3Client, $bucket);

        // Retrieve connection information from the environment or config
        $connectionInfo = [
            'AWS Access Key ID' => config('filesystems.disks.s3.key'),
            'bucket_name' => config('filesystems.disks.s3.bucket'),
            'AWS Region' => config('filesystems.disks.s3.region'),
            'AWS Endpoint' => config('filesystems.disks.s3.endpoint'),
        ];

        // Return the view with paginated files, connection info, total file count, and continuation token
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

        // Delete the file from S3
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
        // Create some test content
        $testContent = "This is a test file uploaded on " . now();

        // Define a test file name with a unique timestamp
        $fileName = 'test-file-' . time() . '.txt';

        // Upload the file to S3
        Storage::disk('s3')->put($fileName, $testContent);

        // Return a success message with the file name
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
                'MaxKeys' => 1000, // Max allowed by S3 per request
            ];

            if ($continuationToken) {
                $params['ContinuationToken'] = $continuationToken;
            }

            // Fetch the list of files
            $result = $s3Client->listObjectsV2($params);

            // Count the number of files in the current batch
            $totalCount += isset($result['KeyCount']) ? $result['KeyCount'] : 0;

            // Check if there's more files to paginate
            $continuationToken = isset($result['NextContinuationToken']) ? $result['NextContinuationToken'] : null;

        } while ($continuationToken); // Continue until there are no more files

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
