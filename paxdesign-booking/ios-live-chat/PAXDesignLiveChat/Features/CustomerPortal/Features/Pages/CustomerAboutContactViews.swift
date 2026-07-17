import SwiftUI

struct CustomerAboutView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var page: CustomerContentPage?
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        ScrollView {
            if let page {
                VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                    if let blocks = page.blocks, !blocks.isEmpty {
                        CustomerNativeContentBlocksView(blocks: blocks)
                            .padding(.horizontal)
                    } else if let body = page.body_text, !body.isEmpty {
                        CustomerPortalCard {
                            Text(body)
                                .font(.body)
                                .foregroundStyle(PAXTheme.textPrimary)
                        }
                        .padding(.horizontal)
                    }

                    if let gallery = page.gallery, !gallery.isEmpty {
                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 12) {
                                CustomerPortalSectionHeader(title: String(localized: "Gallery"))
                                ScrollView(.horizontal, showsIndicators: false) {
                                    HStack(spacing: 12) {
                                        ForEach(gallery, id: \.self) { urlString in
                                            if let url = URL(string: urlString) {
                                                AsyncImage(url: url) { phase in
                                                    if case .success(let image) = phase {
                                                        image.resizable().scaledToFill()
                                                    } else {
                                                        Color.gray.opacity(0.15)
                                                    }
                                                }
                                                .frame(width: 140, height: 100)
                                                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        .padding(.horizontal)
                    }
                }
                .padding(.vertical)
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load"), systemImage: "exclamationmark.triangle", description: Text(error))
                    .padding(.top, 40)
            } else if isLoading {
                ProgressView().padding(.top, 48)
            }
        }
        .background(ServicesCatalogTheme.background.ignoresSafeArea())
        .navigationTitle(page?.title ?? String(localized: "About"))
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(ServicesCatalogTheme.background, for: .navigationBar)
        .toolbarColorScheme(.dark, for: .navigationBar)
        .refreshable { await load(force: true) }
        .task { await load(force: false) }
        .preferredColorScheme(.dark)
    }

    private func load(force: Bool) async {
        if page == nil || force { isLoading = true }
        error = nil
        do {
            page = try await api.fetchContentPage(slug: "ueber-uns")
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

struct CustomerContactView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @State private var page: CustomerContentPage?
    @State private var error: String?
    @State private var isLoading = true
    @State private var showRequestSheet = false

    private let phone = "+43 681 2054 3638"
    private let email = "info@paxdesign.at"
    private let address = "Franzensbrückenstraße 14, 1020 Wien, Österreich"

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                contactHero
                contactMethods
                if let page, let blocks = page.blocks, !blocks.isEmpty {
                    faqSection(from: blocks)
                }
                actionButtons
            }
            .padding(.vertical)
        }
        .background(ServicesCatalogTheme.background.ignoresSafeArea())
        .navigationTitle(String(localized: "Contact"))
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(ServicesCatalogTheme.background, for: .navigationBar)
        .toolbarColorScheme(.dark, for: .navigationBar)
        .refreshable { await load(force: true) }
        .task { await load(force: false) }
        .sheet(isPresented: $showRequestSheet) {
            NavigationStack {
                CustomerCreateOrderView()
                    .environmentObject(api)
            }
        }
        .preferredColorScheme(.dark)
    }

    private var contactHero: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(String(localized: "Ready to elevate your brand or digital product?"))
                .font(.title2.weight(.bold))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
            Text(String(localized: "Let's talk about your project. We develop thoughtful digital solutions and a strong visual presence that positions your brand clearly."))
                .font(.body)
                .foregroundStyle(ServicesCatalogTheme.textSecondary)
        }
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(ServicesCatalogTheme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .padding(.horizontal)
    }

    private var contactMethods: some View {
        VStack(spacing: 12) {
            contactRow(icon: "phone.fill", title: String(localized: "Phone"), value: phone) {
                if let url = URL(string: "tel:\(phone.filter { $0.isNumber || $0 == "+" })") {
                    UIApplication.shared.open(url)
                }
            }
            contactRow(icon: "envelope.fill", title: String(localized: "Email"), value: email) {
                if let url = URL(string: "mailto:\(email)") {
                    UIApplication.shared.open(url)
                }
            }
            contactRow(icon: "mappin.and.ellipse", title: String(localized: "Address"), value: address) {
                let encoded = address.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? address
                if let url = URL(string: "http://maps.apple.com/?address=\(encoded)") {
                    UIApplication.shared.open(url)
                }
            }
        }
        .padding(.horizontal)
    }

    private func contactRow(icon: String, title: String, value: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(alignment: .top, spacing: 14) {
                Image(systemName: icon)
                    .font(.title3)
                    .foregroundStyle(ServicesCatalogTheme.accent)
                    .frame(width: 28)
                VStack(alignment: .leading, spacing: 4) {
                    Text(title)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(ServicesCatalogTheme.textSecondary)
                    Text(value)
                        .font(.body)
                        .foregroundStyle(ServicesCatalogTheme.textPrimary)
                        .multilineTextAlignment(.leading)
                }
                Spacer()
                Image(systemName: "chevron.right")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(.tertiary)
            }
            .padding(16)
            .background(ServicesCatalogTheme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        }
        .buttonStyle(.plain)
    }

    private func faqSection(from blocks: [CustomerContentBlock]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("FAQ")
                .font(.title3.weight(.bold))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
                .padding(.horizontal)

            ForEach(Array(blocks.enumerated()), id: \.offset) { _, block in
                if block.type == "text", let text = block.text, text.count > 80 {
                    DisclosureGroup {
                        Text(text)
                            .font(.subheadline)
                            .foregroundStyle(ServicesCatalogTheme.textSecondary)
                            .padding(.top, 8)
                    } label: {
                        Text(String(text.prefix(60)) + "…")
                            .font(.subheadline.weight(.semibold))
                            .foregroundStyle(ServicesCatalogTheme.textPrimary)
                    }
                    .padding(16)
                    .background(ServicesCatalogTheme.cardBackground)
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                    .padding(.horizontal)
                } else if block.type == "accordion", let items = block.accordionItems {
                    ForEach(Array(items.enumerated()), id: \.offset) { _, item in
                        DisclosureGroup {
                            Text(item.text)
                                .font(.subheadline)
                                .foregroundStyle(ServicesCatalogTheme.textSecondary)
                                .padding(.top, 8)
                        } label: {
                            Text(item.title)
                                .font(.subheadline.weight(.semibold))
                                .foregroundStyle(ServicesCatalogTheme.textPrimary)
                        }
                        .padding(16)
                        .background(ServicesCatalogTheme.cardBackground)
                        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                        .padding(.horizontal)
                    }
                }
            }
        }
    }

    private var actionButtons: some View {
        VStack(spacing: 12) {
            Button(String(localized: "Submit a request")) { showRequestSheet = true }
                .buttonStyle(HomepageContactPrimaryButtonStyle())
            Button(String(localized: "Open Chat")) { navigation.openChat() }
                .buttonStyle(HomepageContactSecondaryButtonStyle())
        }
        .padding(.horizontal)
        .padding(.top, 8)
    }

    private func load(force: Bool) async {
        if page == nil || force { isLoading = true }
        error = nil
        do {
            page = try await api.fetchContentPage(slug: "kontakt")
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct HomepageContactPrimaryButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .foregroundStyle(.black)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 14)
            .background(ServicesCatalogTheme.accent.opacity(configuration.isPressed ? 0.85 : 1))
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}

private struct HomepageContactSecondaryButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .foregroundStyle(ServicesCatalogTheme.textPrimary)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 14)
            .background(ServicesCatalogTheme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}
