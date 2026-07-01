import UIKit

enum ImageUploadPreprocessor {
    /// Resize and compress photos before upload for faster transfer and consistent thumbnails.
    static func prepareForUpload(
        _ data: Data,
        maxDimension: CGFloat = 1600,
        jpegQuality: CGFloat = 0.82
    ) -> (data: Data, filename: String)? {
        guard let image = UIImage(data: data) else { return nil }
        let resized = resize(image, maxDimension: maxDimension)
        guard let jpeg = resized.jpegData(compressionQuality: jpegQuality) else { return nil }
        return (jpeg, "photo.jpg")
    }

    private static func resize(_ image: UIImage, maxDimension: CGFloat) -> UIImage {
        let size = image.size
        let longest = max(size.width, size.height)
        guard longest > maxDimension else { return image }

        let scale = maxDimension / longest
        let target = CGSize(width: size.width * scale, height: size.height * scale)
        let format = UIGraphicsImageRendererFormat.default()
        format.scale = 1
        return UIGraphicsImageRenderer(size: target, format: format).image { _ in
            image.draw(in: CGRect(origin: .zero, size: target))
        }
    }
}
