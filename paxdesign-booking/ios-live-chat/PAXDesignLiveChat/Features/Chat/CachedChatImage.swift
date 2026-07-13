import SwiftUI

enum ChatImageCache {
    private static let cache: URLCache = {
        URLCache(
            memoryCapacity: 32 * 1024 * 1024,
            diskCapacity: 128 * 1024 * 1024,
            diskPath: "pax-chat-images"
        )
    }()

    static let session: URLSession = {
        let config = URLSessionConfiguration.default
        config.requestCachePolicy = .returnCacheDataElseLoad
        config.urlCache = cache
        config.timeoutIntervalForRequest = 20
        return URLSession(configuration: config)
    }()

    static func clearAll() {
        cache.removeAllCachedResponses()
        URLCache.shared.removeAllCachedResponses()
    }

    static func cachedImage(for url: URL) async -> UIImage? {
        let request = URLRequest(url: url, cachePolicy: .returnCacheDataElseLoad)
        if let cached = cache.cachedResponse(for: request),
           let image = UIImage(data: cached.data) {
            return image
        }
        do {
            let (data, response) = try await session.data(for: request)
            guard let image = UIImage(data: data) else { return nil }
            let stored = CachedURLResponse(response: response, data: data)
            cache.storeCachedResponse(stored, for: request)
            return image
        } catch {
            return nil
        }
    }
}

struct CachedChatImage: View {
    let url: URL
    let onTap: () -> Void

    @State private var image: UIImage?
    @State private var failed = false

    var body: some View {
        Button(action: onTap) {
            Group {
                if let image {
                    Image(uiImage: image)
                        .resizable()
                        .scaledToFit()
                        .frame(maxWidth: PAXMessageStyle.imageMaxWidth)
                } else if failed {
                    placeholder
                } else {
                    ZStack {
                        placeholder
                        PAXSkeletonBlock(width: 54, height: 6, cornerRadius: 5)
                            .frame(width: 74, height: 26)
                            .background(
                                Capsule(style: .continuous)
                                    .fill(PAXTheme.surface.opacity(0.68))
                            )
                    }
                }
            }
            .frame(maxHeight: PAXMessageStyle.imageMaxHeight)
            .clipShape(RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous)
                    .stroke(Color.white.opacity(0.08), lineWidth: 0.5)
            )
        }
        .buttonStyle(.plain)
        .task(id: url) {
            image = await ChatImageCache.cachedImage(for: url)
            failed = image == nil
        }
    }

    private var placeholder: some View {
        RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous)
            .fill(PAXTheme.surface.opacity(0.4))
            .frame(width: 140, height: 100)
            .overlay {
                PAXIcon("photo", size: .card, emphasis: .tertiary)
            }
    }
}
