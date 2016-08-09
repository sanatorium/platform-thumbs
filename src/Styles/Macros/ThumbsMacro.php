<?php

namespace Sanatorium\Thumbs\Styles\Macros;

use Illuminate\Support\Str;
use Cartalyst\Filesystem\File;
use Platform\Media\Models\Media;
use Illuminate\Container\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Platform\Media\Styles\Macros\AbstractMacro;
use Platform\Media\Styles\Macros\MacroInterface;
use Storage;

class ThumbsMacro extends AbstractMacro implements MacroInterface
{
    /**
     * The Illuminate Container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $app;

    /**
     * The Filesystem instance.
     *
     * @var \Cartalyst\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * The Intervention Image Manager instance.
     *
     * @var \Intervention\Image\ImageManager
     */
    protected $intervention;

    /**
     * Constructor.
     *
     * @param  \Illuminate\Container\Container  $app
     * @return void
     */
    public function __construct(Container $app)
    {
        $this->app = $app;

        $this->intervention = $app['image'];

        $this->filesystem = $app['cartalyst.filesystem'];
    }

    /**
     * {@inheritDoc}
     */
    public function up(Media $media, File $file, UploadedFile $uploadedFile)
    {
        // Check if the file is an image
        if ($file->isImage()) {
            $path = $this->getPath($file, $media);

            // Create the thumbnail
            $image = $this->intervention->make($file->getContents());

            if ( $this->style->width )
            {
                $image->resize(null, $this->style->width, function ($constraint)
                {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            $image->encode($extension = mime2Extension($media->mime));

            Storage::disk('s3')->put(
                str_replace(public_path(), null, $path),
                $image->getEncoded()
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function down(Media $media, File $file)
    {
        $path = $this->getPath($file, $media);

        \Illuminate\Support\Facades\File::delete($path);
    }

    /**
     * Returns the prepared file path.
     *
     * @param  \Cartalyst\Filesystem\File  $file
     * @param  \Platform\Media\Models\Media  $media
     * @return string
     */
    protected function getPath(File $file, Media $media)
    {
        $width = $this->style->width;
        $height = $this->style->height;

        $name = Str::slug(implode('-', [ $width, $height ?: $width ]));

        $extension = mime2Extension($media->mime);

        return "{$this->style->path}/{$media->id}_{$name}.{$extension}";
    }



}
