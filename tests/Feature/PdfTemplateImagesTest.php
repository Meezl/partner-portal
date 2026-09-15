<?php

use Illuminate\Support\Facades\File;

/*
 * DomPDF embeds JPEGs as they are, but needs the GD extension to decode PNG,
 * GIF, BMP or WebP. A server without GD then fails the whole document with
 * "The PHP GD extension is required" — which is how the visa letter broke in
 * production over its PNG signature. Keeping every PDF image a JPEG means the
 * documents render whether or not GD is installed.
 */
it('embeds only JPEG images in generated PDFs', function () {
    $images = collect(File::allFiles(resource_path('views/pdf')))
        ->flatMap(function ($template) {
            preg_match_all("/public_path\\(\\s*'([^']+)'\\s*\\)/", $template->getContents(), $matches);

            return collect($matches[1])->map(fn (string $path) => [$template->getRelativePathname(), $path]);
        })
        // Images named in config rather than in the template itself.
        ->push(['config ahaic.visa_signatory.signature', config('ahaic.visa_signatory.signature')]);

    expect($images)->not->toBeEmpty();

    foreach ($images as [$template, $path]) {
        expect(strtolower(pathinfo($path, PATHINFO_EXTENSION)))
            ->toBeIn(['jpg', 'jpeg'], "{$template} embeds {$path}; convert it to JPEG so the PDF renders without GD.")
            ->and(file_exists(public_path($path)))
            ->toBeTrue("{$template} embeds {$path}, which does not exist.");
    }
});
