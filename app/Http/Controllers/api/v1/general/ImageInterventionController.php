<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;
use Laravel\Reverb\Loggers\Log;

class ImageInterventionController extends Controller
{
    public function show($path)
    {
        // Get the requested image path
        $imagePath = storage_path('app/private/' . $path);

        // Check if the file exists
        if (!File::exists($imagePath)) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        // Check if the file is an SVG
        if (Str::endsWith($imagePath, '.svg')) {
            // Return the SVG file content directly
            $svgContent = File::get($imagePath);
            return response($svgContent, 200)->header('Content-Type', 'image/svg+xml');
        }

        try {
            // Get the requested width and height from the query string
            $width = request()->query('w');
            $height = request()->query('h');

            // Read the image
            $image = Image::read($imagePath);

            // Scale down if width and height are provided
            if ($width && $height) {
                $image->scaleDown($width, $height);
            }

            return response()->image($image, Format::WEBP, quality: 65);
        } catch (\Intervention\Image\Exceptions\DecoderException $e) {
            Log::info('image \Intervention\Image\Exceptions\DecoderException', $path);
            // Handle the case where the image cannot be decoded
            return response()->json(['error' => 'Unable to process image'], 400);
        } catch (\Exception $e) {
            Log::info('image exception', $path);
            // Handle any other unexpected errors
            return response()->json(['error' => 'An error occurred while processing the image'], 500);
        }
    }
}
