import SwiftUI
import WebKit

struct FilePreviewSheet: View {
    let url: URL
    let fileName: String
    let mimeType: String?

    @Environment(\.dismiss) private var dismiss
    @State private var scale: CGFloat = 1

    private var isSVG: Bool {
        let lower = url.absoluteString.lowercased()
        if lower.hasSuffix(".svg") { return true }
        if let mime = mimeType?.lowercased(), mime.contains("svg") { return true }
        if let name = fileName.lowercased() as String?, name.hasSuffix(".svg") { return true }
        return false
    }

    private var isRasterImage: Bool {
        if isSVG { return false }
        let lower = url.absoluteString.lowercased()
        if lower.hasSuffix(".png") || lower.hasSuffix(".jpg") || lower.hasSuffix(".jpeg")
            || lower.hasSuffix(".gif") || lower.hasSuffix(".webp") || lower.hasSuffix(".heic") {
            return true
        }
        if let mime = mimeType?.lowercased() {
            return mime.hasPrefix("image/")
        }
        return false
    }

    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.opacity(0.96).ignoresSafeArea()

                if isSVG {
                    SVGPreviewWebView(url: url)
                        .padding(.horizontal, 12)
                        .padding(.vertical, 8)
                } else if isRasterImage {
                    AsyncImage(url: url) { phase in
                        switch phase {
                        case .success(let image):
                            ScrollView([.horizontal, .vertical], showsIndicators: false) {
                                image
                                    .resizable()
                                    .scaledToFit()
                                    .scaleEffect(scale)
                                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                                    .gesture(
                                        MagnificationGesture()
                                            .onChanged { value in scale = max(0.5, min(6, value)) }
                                            .onEnded { _ in
                                                withAnimation(PAXTheme.quickSpring) {
                                                    scale = max(1, min(scale, 4))
                                                }
                                            }
                                    )
                            }
                        case .failure:
                            fallbackContent
                        default:
                            PAXTimelineLoaderCard(status: L10n.ChatLoadingImage)
                                .frame(maxWidth: 260)
                        }
                    }
                } else {
                    fallbackContent
                }
            }
            .navigationTitle(fileName.isEmpty ? L10n.TeamOpenFile : fileName)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(L10n.CommonClose) { dismiss() }
                }
                ToolbarItem(placement: .primaryAction) {
                    ShareLink(item: url) {
                        PAXIcon("square.and.arrow.up", size: .row)
                    }
                }
            }
        }
    }

    private var fallbackContent: some View {
        VStack(spacing: 16) {
            PAXIcon("doc.text", size: .hero, emphasis: .tertiary)
            Text(fileName.isEmpty ? L10n.TeamOpenFile : fileName)
                .font(.headline)
                .foregroundStyle(.white)
                .multilineTextAlignment(.center)
            Button(L10n.TeamOpenFile) {
                UIApplication.shared.open(url)
            }
            .buttonStyle(.borderedProminent)
        }
        .padding(24)
    }
}

private struct SVGPreviewWebView: UIViewRepresentable {
    let url: URL

    func makeUIView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        let webView = WKWebView(frame: .zero, configuration: config)
        webView.isOpaque = false
        webView.backgroundColor = .clear
        webView.scrollView.minimumZoomScale = 0.5
        webView.scrollView.maximumZoomScale = 8
        webView.scrollView.bouncesZoom = true
        webView.navigationDelegate = context.coordinator
        context.coordinator.load(url: url, into: webView)
        return webView
    }

    func updateUIView(_ uiView: WKWebView, context: Context) {}

    func makeCoordinator() -> Coordinator {
        Coordinator()
    }

    final class Coordinator: NSObject, WKNavigationDelegate {
        func load(url: URL, into webView: WKWebView) {
            let html = """
            <!DOCTYPE html>
            <html>
            <head>
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=8, user-scalable=yes">
            <style>
              html, body { margin:0; padding:0; width:100%; height:100%; background:transparent; overflow:hidden; }
              #wrap { width:100%; height:100%; display:flex; align-items:center; justify-content:center; }
              img, svg { max-width:100%; max-height:100%; width:auto; height:auto; }
            </style>
            </head>
            <body>
              <div id="wrap">
                <img src="\(url.absoluteString)" alt="" />
              </div>
            </body>
            </html>
            """
            webView.loadHTMLString(html, baseURL: url.deletingLastPathComponent())
        }
    }
}
