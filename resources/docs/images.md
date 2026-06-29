# Image Processing

This is about how your programming language allows you handle images, not just as files, but in being able to manipulate them in terms of cropping, resizing, retouching and enhancing etc.

---

## The Dorguzen Image Engine — DGZ_Thumbnail

In Dorguzen, image processing is handled by the **DGZ_Thumbnail** engine, which is built on PHP's GD library. It provides PHP GD-based proportional resize, quality control, and WebP support.

When you call `move('resize')` on a `DGZ_Uploader` instance, the resize pipeline runs through `DGZ_Thumbnail::create()`, which uses `imagecopyresampled()` for high-quality bilinear resampling, calculates a proportional thumbnail size, applies quality control, and saves the thumbnail.

The full reference for image processing — including `DGZ_Upload`, `DGZ_Uploader`, the `DGZ_Thumbnail` engine, controlling thumbnail dimensions and quality, generating a thumbnail from an already-uploaded file, supported file types, and displaying uploaded images in a view — is documented under [File Uploads]({{base}}docs/file-uploads).

---

Return to [Introduction]({{base}}docs/introduction) or use the sidebar to navigate.
