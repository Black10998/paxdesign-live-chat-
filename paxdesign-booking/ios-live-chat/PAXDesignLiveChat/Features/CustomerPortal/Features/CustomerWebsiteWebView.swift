import SwiftUI
import WebKit

/// Loads the live WordPress site (Elementor, menus, services, full layout) inside the app.
@MainActor
final class CustomerWebsiteController: ObservableObject {
    static let shared = CustomerWebsiteController()

    @Published var canGoBack = false
    @Published var canGoForward = false
    @Published var isLoading = false
    @Published var progress: Double = 0
    @Published var pageTitle: String = ""
    @Published var currentURL: URL?
    @Published var lastError: String?

    weak var webView: WKWebView?

    func loadHome() {
        load(url: URL(string: AppServerConfig.siteURL)!)
    }

    func load(url: URL) {
        lastError = nil
        guard let webView else { return }
        var request = URLRequest(url: url)
        request.cachePolicy = .useProtocolCachePolicy
        webView.load(request)
    }

    func load(path: String) {
        let trimmed = path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        let base = AppServerConfig.siteURL.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        let urlString = trimmed.isEmpty ? base : "\(base)/\(trimmed)/"
        guard let url = URL(string: urlString) else { return }
        load(url: url)
    }

    func reload() {
        lastError = nil
        webView?.reload()
    }

    func goBack() {
        webView?.goBack()
    }

    func goForward() {
        webView?.goForward()
    }

    func openCustomerPortal() {
        evaluate("if(window.PDXAuth&&PDXAuth.openCustomerPortal){PDXAuth.openCustomerPortal();}else if(window.PDXAuth&&PDXAuth.openLogin){PDXAuth.openLogin();}")
    }

    func evaluate(_ script: String) {
        webView?.evaluateJavaScript(script, completionHandler: nil)
    }
}

struct CustomerWebsiteWebView: UIViewRepresentable {
    @ObservedObject var controller: CustomerWebsiteController

    func makeCoordinator() -> Coordinator {
        Coordinator(controller: controller)
    }

    func makeUIView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.defaultWebpagePreferences.allowsContentJavaScript = true
        config.websiteDataStore = .default()
        config.allowsInlineMediaPlayback = true
        config.mediaTypesRequiringUserActionForPlayback = []

        let webView = WKWebView(frame: .zero, configuration: config)
        webView.customUserAgent = Self.userAgent
        webView.navigationDelegate = context.coordinator
        webView.uiDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        webView.isOpaque = false
        webView.backgroundColor = .systemBackground
        webView.scrollView.contentInsetAdjustmentBehavior = .automatic

        controller.webView = webView
        context.coordinator.observeProgress(webView)

        if controller.currentURL == nil {
            controller.loadHome()
        }

        return webView
    }

    func updateUIView(_ uiView: WKWebView, context: Context) {}

    static var userAgent: String {
        let version = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "2.1"
        let build = Bundle.main.infoDictionary?["CFBundleVersion"] as? String ?? "1"
        return "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 PAXDesign-iOS/\(version) (\(build))"
    }

    final class Coordinator: NSObject, WKNavigationDelegate, WKUIDelegate {
        private let controller: CustomerWebsiteController
        private var progressObservation: NSKeyValueObservation?

        init(controller: CustomerWebsiteController) {
            self.controller = controller
        }

        func observeProgress(_ webView: WKWebView) {
            progressObservation = webView.observe(\.estimatedProgress, options: [.new]) { [weak self] view, _ in
                Task { @MainActor in
                    self?.controller.progress = view.estimatedProgress
                    self?.controller.isLoading = view.estimatedProgress < 1.0
                }
            }
        }

        func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
            Task { @MainActor in
                controller.isLoading = true
                controller.lastError = nil
                controller.canGoBack = webView.canGoBack
                controller.canGoForward = webView.canGoForward
            }
        }

        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            Task { @MainActor in
                controller.isLoading = false
                controller.progress = 1
                controller.pageTitle = webView.title ?? ""
                controller.currentURL = webView.url
                controller.canGoBack = webView.canGoBack
                controller.canGoForward = webView.canGoForward
            }
        }

        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            Task { @MainActor in
                controller.isLoading = false
                controller.lastError = error.localizedDescription
            }
        }

        func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
            Task { @MainActor in
                controller.isLoading = false
                controller.lastError = error.localizedDescription
            }
        }

        func webView(
            _ webView: WKWebView,
            decidePolicyFor navigationAction: WKNavigationAction,
            decisionHandler: @escaping (WKNavigationActionPolicy) -> Void
        ) {
            guard let url = navigationAction.request.url else {
                decisionHandler(.allow)
                return
            }
            if url.scheme == "tel" || url.scheme == "mailto" {
                UIApplication.shared.open(url)
                decisionHandler(.cancel)
                return
            }
            if let host = url.host?.lowercased(),
               host.contains("paxdesign.at") || host.hasSuffix(".paxdesign.at") {
                decisionHandler(.allow)
                return
            }
            if navigationAction.navigationType == .linkActivated {
                UIApplication.shared.open(url)
                decisionHandler(.cancel)
                return
            }
            decisionHandler(.allow)
        }

        func webView(
            _ webView: WKWebView,
            createWebViewWith configuration: WKWebViewConfiguration,
            for navigationAction: WKNavigationAction,
            windowFeatures: WKWindowFeatures
        ) -> WKWebView? {
            if navigationAction.targetFrame == nil, let url = navigationAction.request.url {
                webView.load(URLRequest(url: url))
            }
            return nil
        }
    }
}

struct CustomerWebsiteTabView: View {
    @ObservedObject private var controller = CustomerWebsiteController.shared
    @StateObject private var network = CustomerNetworkMonitor.shared
    @State private var showMenu = false
    @State private var navigation: CustomerContentNavigation?

    var body: some View {
        NavigationStack {
            ZStack(alignment: .top) {
                CustomerWebsiteWebView(controller: controller)
                    .ignoresSafeArea(edges: .bottom)

                if controller.isLoading && controller.progress < 0.95 {
                    GeometryReader { geo in
                        Rectangle()
                            .fill(PAXTheme.accent)
                            .frame(width: max(0, geo.size.width * controller.progress), height: 3)
                    }
                    .frame(height: 3)
                    .animation(.linear(duration: 0.15), value: controller.progress)
                }

                if !network.isConnected {
                    VStack {
                        HStack {
                            Image(systemName: "wifi.slash")
                            Text(String(localized: "Offline — reconnecting when network is available"))
                                .font(.caption)
                        }
                        .padding(8)
                        .frame(maxWidth: .infinity)
                        .background(Color.orange.opacity(0.92))
                        .foregroundStyle(.white)
                        Spacer()
                    }
                }

                if let error = controller.lastError {
                    websiteErrorOverlay(error)
                }
            }
            .navigationTitle(controller.pageTitle.isEmpty ? "PAXDesign" : controller.pageTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarLeading) {
                    Button {
                        showMenu = true
                    } label: {
                        Image(systemName: "line.3.horizontal")
                    }
                    .accessibilityLabel(String(localized: "Site menu"))
                }
                ToolbarItemGroup(placement: .topBarTrailing) {
                    Button { controller.goBack() } label: {
                        Image(systemName: "chevron.left")
                    }
                    .disabled(!controller.canGoBack)
                    Button { controller.goForward() } label: {
                        Image(systemName: "chevron.right")
                    }
                    .disabled(!controller.canGoForward)
                    Button { controller.reload() } label: {
                        Image(systemName: "arrow.clockwise")
                    }
                }
            }
            .sheet(isPresented: $showMenu) {
                CustomerWebsiteMenuSheet(navigation: navigation) { item in
                    showMenu = false
                    navigateMenuItem(item)
                } onHome: {
                    showMenu = false
                    controller.loadHome()
                } onPortal: {
                    showMenu = false
                    controller.openCustomerPortal()
                }
            }
            .task {
                if navigation == nil {
                    navigation = try? await CustomerSessionController.shared.api.fetchContentNavigation()
                }
            }
        }
    }

    @ViewBuilder
    private func websiteErrorOverlay(_ error: String) -> some View {
        VStack(spacing: 16) {
            Spacer()
            PAXContentUnavailableView(
                String(localized: "Page could not be loaded"),
                systemImage: "wifi.exclamationmark",
                description: Text(error)
            )
            Button(String(localized: "Try again")) { controller.reload() }
                .buttonStyle(.borderedProminent)
            Spacer()
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(.ultraThinMaterial)
    }

    private func navigateMenuItem(_ item: CustomerContentNavigation.MenuItem) {
        if let url = item.url, !url.isEmpty, let parsed = URL(string: url) {
            controller.load(url: parsed)
            return
        }
        if !item.slug.isEmpty {
            controller.load(path: item.slug)
        }
    }
}

struct CustomerWebsiteMenuSheet: View {
    let navigation: CustomerContentNavigation?
    var onSelect: (CustomerContentNavigation.MenuItem) -> Void
    var onHome: () -> Void
    var onPortal: () -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            List {
                Section {
                    Button {
                        onHome()
                        dismiss()
                    } label: {
                        Label(String(localized: "Home"), systemImage: "house.fill")
                    }
                    Button {
                        onPortal()
                        dismiss()
                    } label: {
                        Label(String(localized: "Customer portal"), systemImage: "person.crop.circle")
                    }
                }
                if let sections = navigation?.sections, !sections.isEmpty {
                    ForEach(sections) { section in
                        Section(section.title) {
                            ForEach(section.items) { item in
                                CustomerWebsiteMenuItemRow(item: item, depth: 0, onSelect: onSelect)
                            }
                        }
                    }
                } else if let menus = navigation?.menus, !menus.isEmpty {
                    ForEach(menus) { menu in
                        Section(menu.title) {
                            ForEach(menu.items) { item in
                                CustomerWebsiteMenuItemRow(item: item, depth: 0, onSelect: onSelect)
                            }
                        }
                    }
                } else {
                    Section(String(localized: "Browse")) {
                        Text(String(localized: "Use the website header menus while browsing, or pull to refresh the home page."))
                            .font(.footnote)
                            .foregroundStyle(.secondary)
                    }
                }
            }
            .navigationTitle(String(localized: "Menu"))
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(String(localized: "Close")) { dismiss() }
                }
            }
        }
        .presentationDetents([.medium, .large])
    }
}

private struct CustomerWebsiteMenuItemRow: View {
    let item: CustomerContentNavigation.MenuItem
    let depth: Int
    var onSelect: (CustomerContentNavigation.MenuItem) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Button {
                onSelect(item)
            } label: {
                Text(item.title)
                    .padding(.leading, CGFloat(depth) * 16)
            }
            if let children = item.children {
                ForEach(children) { child in
                    CustomerWebsiteMenuItemRow(item: child, depth: depth + 1, onSelect: onSelect)
                }
            }
        }
    }
}

/// Embeds a single site page — used when deep-linking to marketing content.
struct CustomerSitePageWebView: View {
    let path: String
    let title: String
    @StateObject private var controller = CustomerWebsiteController()

    var body: some View {
        ZStack(alignment: .top) {
            CustomerWebsiteWebView(controller: controller)
            if controller.isLoading && controller.progress < 0.95 {
                ProgressView(value: controller.progress)
                    .progressViewStyle(.linear)
                    .padding(.horizontal)
            }
        }
        .navigationTitle(title)
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            controller.load(path: path)
        }
    }
}
