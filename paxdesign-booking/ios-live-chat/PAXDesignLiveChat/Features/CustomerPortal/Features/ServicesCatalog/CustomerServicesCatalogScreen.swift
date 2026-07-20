import SwiftUI

/// Apple-quality Services experience — clean black-and-white presentation
/// with premium typography, large imagery, and restrained PAX accent on CTAs only.
struct CustomerServicesCatalogScreen: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.colorScheme) private var colorScheme
    @StateObject private var network = CustomerNetworkMonitor.shared

    @State private var catalog: CustomerServicesCatalogResponse?
    @State private var mediaBySlug: [String: CustomerServicesResponse.Service] = [:]
    @State private var language: CustomerServicesCatalogLanguage = {
        let code = Locale.current.language.languageCode?.identifier ?? "de"
        return CustomerServicesCatalogLanguage(rawValue: code) ?? .de
    }()
    @State private var expandedCardIDs: Set<String> = []
    @State private var spotlightCardID: String?
    @State private var error: String?
    @State private var isLoading = true
    @State private var showRequestSheet = false
    @State private var requestSlug = ""
    @State private var requestTitle = ""
    @State private var requestDescription = ""

    private var canvas: Color {
        colorScheme == .dark ? .black : .white
    }

    private var ink: Color {
        colorScheme == .dark ? .white : .black
    }

    private var inkSecondary: Color {
        colorScheme == .dark ? Color.white.opacity(0.62) : Color.black.opacity(0.55)
    }

    private var hairline: Color {
        colorScheme == .dark ? Color.white.opacity(0.12) : Color.black.opacity(0.08)
    }

    private var panel: Color {
        colorScheme == .dark ? Color(white: 0.07) : Color(white: 0.97)
    }

    var body: some View {
        NavigationStack {
            Group {
                if isLoading && catalog == nil {
                    CustomerServicesCatalogSkeleton()
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else if let error, catalog == nil {
                    servicesErrorView(error)
                } else if let catalog {
                    catalogScroll(catalog)
                }
            }
            .background(canvas.ignoresSafeArea())
            .navigationTitle(catalog?.title ?? String(localized: "Services"))
            .navigationBarTitleDisplayMode(.large)
            .toolbarBackground(canvas, for: .navigationBar)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    ServicesLanguageSwitcher(language: $language)
                }
            }
            // No shared portal quick-menu chrome here — it crowded the bar and served no
            // Services-specific purpose (Account destinations remain on the Account tab).
            .refreshable { await load(force: true) }
            .task(id: language.rawValue) { await load(force: false) }
            .sheet(isPresented: $showRequestSheet) {
                NavigationStack {
                    CustomerCreateOrderView(
                        preselectedSlug: requestSlug,
                        prefilledTitle: requestTitle,
                        prefilledDescription: requestDescription
                    )
                    .environmentObject(api)
                }
            }
            .onChange(of: navigation.pendingOrderSlug) { slug in
                guard let slug, !slug.isEmpty else { return }
                if let card = catalog?.cards.first(where: { $0.order_slug == slug || $0.id == slug }) {
                    openRequest(for: card)
                } else {
                    requestSlug = slug
                    requestTitle = ""
                    requestDescription = CustomerCreateOrderView.templateDescription(title: slug, features: [])
                    showRequestSheet = true
                }
                navigation.pendingOrderSlug = nil
            }
        }
    }

    @ViewBuilder
    private func catalogScroll(_ catalog: CustomerServicesCatalogResponse) -> some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 0) {
                    heroHeader(catalog)
                    statementBand(catalog)
                    servicesList(catalog)
                    if !catalog.process_steps.isEmpty {
                        processSection(catalog)
                    }
                }
                .padding(.bottom, 48)
            }
            .onAppear {
                if let id = navigation.pendingServiceCardID {
                    scrollToCard(id, proxy: proxy, animated: true)
                    navigation.pendingServiceCardID = nil
                }
            }
            .onChange(of: spotlightCardID) { id in
                guard let id else { return }
                scrollToCard(id, proxy: proxy, animated: true)
            }
        }
        .environment(\.layoutDirection, catalog.isRTL ? .rightToLeft : .leftToRight)
    }

    private func heroHeader(_ catalog: CustomerServicesCatalogResponse) -> some View {
        VStack(alignment: .leading, spacing: 14) {
            Text(catalog.subtitle)
                .font(.system(.title3, design: .default).weight(.regular))
                .foregroundStyle(inkSecondary)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 22)
        .padding(.top, 8)
        .padding(.bottom, 28)
    }

    private func statementBand(_ catalog: CustomerServicesCatalogResponse) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Rectangle()
                .fill(hairline)
                .frame(height: 1)
            Text(catalog.statement)
                .font(.system(.body, design: .default))
                .foregroundStyle(ink)
                .lineSpacing(5)
                .fixedSize(horizontal: false, vertical: true)
                .padding(.horizontal, 22)
                .padding(.vertical, 28)
            Rectangle()
                .fill(hairline)
                .frame(height: 1)
        }
        .padding(.bottom, 8)
    }

    private func servicesList(_ catalog: CustomerServicesCatalogResponse) -> some View {
        LazyVStack(spacing: 0) {
            ForEach(Array(catalog.cards.enumerated()), id: \.element.id) { index, card in
                PremiumServiceRow(
                    card: card,
                    catalog: catalog,
                    imageURL: ServiceCatalogImagery.imageURL(for: card, apiMedia: media(for: card)),
                    isExpanded: expandedCardIDs.contains(card.id),
                    isSpotlight: spotlightCardID == card.id,
                    ink: ink,
                    inkSecondary: inkSecondary,
                    hairline: hairline,
                    panel: panel,
                    onToggleDetails: { toggleExpanded(card.id) },
                    onBook: { openRequest(for: card) }
                )
                .id(card.id)
                .padding(.horizontal, 22)
                .padding(.vertical, 28)

                if index < catalog.cards.count - 1
                    || card.id == catalog.security_section.after_card_id {
                    Rectangle()
                        .fill(hairline)
                        .frame(height: 1)
                        .padding(.horizontal, 22)
                }

                if card.id == catalog.security_section.after_card_id {
                    securityBreak(catalog)
                    Rectangle()
                        .fill(hairline)
                        .frame(height: 1)
                        .padding(.horizontal, 22)
                }
            }
        }
        .padding(.top, 12)
    }

    private func securityBreak(_ catalog: CustomerServicesCatalogResponse) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(catalog.security_section.title)
                .font(.system(.title2, design: .default).weight(.semibold))
                .foregroundStyle(ink)
            Text(catalog.security_section.subtitle)
                .font(.system(.body, design: .default))
                .foregroundStyle(inkSecondary)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 22)
        .padding(.vertical, 36)
        .background(panel)
    }

    private func processSection(_ catalog: CustomerServicesCatalogResponse) -> some View {
        VStack(alignment: .leading, spacing: 28) {
            Text(catalog.process_title)
                .font(.system(.title2, design: .default).weight(.semibold))
                .foregroundStyle(ink)
                .padding(.horizontal, 22)
                .padding(.top, 40)

            VStack(spacing: 0) {
                ForEach(Array(catalog.process_steps.enumerated()), id: \.offset) { index, step in
                    HStack(alignment: .top, spacing: 18) {
                        Text(String(format: "%02d", index + 1))
                            .font(.system(.footnote, design: .monospaced).weight(.semibold))
                            .foregroundStyle(inkSecondary)
                            .frame(width: 28, alignment: .leading)
                        VStack(alignment: .leading, spacing: 6) {
                            Text(step.title)
                                .font(.system(.headline, design: .default).weight(.semibold))
                                .foregroundStyle(ink)
                            Text(step.text)
                                .font(.system(.subheadline, design: .default))
                                .foregroundStyle(inkSecondary)
                                .fixedSize(horizontal: false, vertical: true)
                        }
                        Spacer(minLength: 0)
                    }
                    .padding(.horizontal, 22)
                    .padding(.vertical, 18)

                    if index < catalog.process_steps.count - 1 {
                        Rectangle()
                            .fill(hairline)
                            .frame(height: 1)
                            .padding(.leading, 68)
                    }
                }
            }
        }
    }

    private func servicesErrorView(_ message: String) -> some View {
        VStack(spacing: 18) {
            PAXIcon(
                network.isConnected ? "exclamationmark.triangle" : "wifi.slash",
                size: .display,
                tint: ink
            )
            Text(message)
                .multilineTextAlignment(.center)
                .foregroundStyle(inkSecondary)
                .font(.system(.body, design: .default))
            Button(String(localized: "Try again")) {
                Task { await load(force: true) }
            }
            .font(.system(.body, design: .default).weight(.semibold))
            .foregroundStyle(canvas)
            .padding(.horizontal, 22)
            .padding(.vertical, 12)
            .background(ink)
            .clipShape(Capsule())
            .buttonStyle(.plain)
        }
        .padding(32)
    }

    private func media(for card: CustomerServicesCatalogResponse.Card) -> CustomerServicesResponse.Service? {
        mediaBySlug[card.order_slug]
            ?? mediaBySlug[card.id]
            ?? mediaBySlug.first(where: { $0.key.localizedCaseInsensitiveContains(card.order_slug) })?.value
    }

    private func toggleExpanded(_ id: String) {
        if expandedCardIDs.contains(id) {
            expandedCardIDs.remove(id)
        } else {
            expandedCardIDs.insert(id)
        }
    }

    private func openRequest(for card: CustomerServicesCatalogResponse.Card) {
        requestSlug = card.order_slug
        requestTitle = card.title
        requestDescription = CustomerCreateOrderView.templateDescription(title: card.title, features: card.features)
        showRequestSheet = true
    }

    private func scrollToCard(_ id: String, proxy: ScrollViewProxy, animated: Bool) {
        spotlightCardID = id
        if animated {
            withAnimation(.easeInOut(duration: 0.45)) {
                proxy.scrollTo(id, anchor: .center)
            }
        } else {
            proxy.scrollTo(id, anchor: .center)
        }
        DispatchQueue.main.asyncAfter(deadline: .now() + 2.0) {
            if spotlightCardID == id { spotlightCardID = nil }
        }
    }

    private func load(force: Bool) async {
        if catalog == nil || force { isLoading = true }
        error = nil
        do {
            async let catalogTask = api.fetchServicesCatalog(lang: language.rawValue)
            async let mediaTask = api.fetchServices()
            let loadedCatalog = try await catalogTask
            catalog = loadedCatalog
            if let media = try? await mediaTask {
                var map: [String: CustomerServicesResponse.Service] = [:]
                for service in media.services {
                    map[service.slug] = service
                    if let key = service.icon_key, !key.isEmpty {
                        map[key] = service
                    }
                }
                mediaBySlug = map
            }
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

// MARK: - Premium service row

private struct PremiumServiceRow: View {
    @Environment(\.colorScheme) private var colorScheme

    let card: CustomerServicesCatalogResponse.Card
    let catalog: CustomerServicesCatalogResponse
    let imageURL: URL?
    let isExpanded: Bool
    let isSpotlight: Bool
    let ink: Color
    let inkSecondary: Color
    let hairline: Color
    let panel: Color
    let onToggleDetails: () -> Void
    let onBook: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            mediaHero

            VStack(alignment: .leading, spacing: 10) {
                if let badge = badgeLabel {
                    Text(badge)
                        .font(.system(.caption2, design: .default).weight(.semibold))
                        .tracking(1.2)
                        .textCase(.uppercase)
                        .foregroundStyle(inkSecondary)
                }

                Text(card.title)
                    .font(.system(.title2, design: .default).weight(.semibold))
                    .foregroundStyle(ink)
                    .fixedSize(horizontal: false, vertical: true)

                Text(card.description)
                    .font(.system(.body, design: .default))
                    .foregroundStyle(inkSecondary)
                    .lineSpacing(3)
                    .fixedSize(horizontal: false, vertical: true)
            }

            if !card.features.isEmpty {
                VStack(alignment: .leading, spacing: 10) {
                    ForEach(card.features, id: \.self) { feature in
                        HStack(alignment: .top, spacing: 10) {
                            Circle()
                                .fill(ink)
                                .frame(width: 4, height: 4)
                                .padding(.top, 7)
                            Text(feature)
                                .font(.system(.subheadline, design: .default))
                                .foregroundStyle(ink)
                                .fixedSize(horizontal: false, vertical: true)
                        }
                    }
                }
                .padding(.top, 2)
            }

            HStack(spacing: 14) {
                Button(action: onBook) {
                    Text(catalog.book_label)
                        .font(.system(.body, design: .default).weight(.semibold))
                        .foregroundStyle(colorScheme == .dark ? .black : .white)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 14)
                        .background(ink)
                        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                }
                .buttonStyle(.plain)

                if !card.details.isEmpty {
                    Button(action: onToggleDetails) {
                        Text(isExpanded ? catalog.less_label : catalog.more_label)
                            .font(.system(.body, design: .default).weight(.medium))
                            .foregroundStyle(ink)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .background(panel)
                            .overlay(
                                RoundedRectangle(cornerRadius: 14, style: .continuous)
                                    .stroke(hairline, lineWidth: 1)
                            )
                            .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                    }
                    .buttonStyle(.plain)
                }
            }

            if isExpanded, !card.details.isEmpty {
                VStack(alignment: .leading, spacing: 18) {
                    Rectangle()
                        .fill(hairline)
                        .frame(height: 1)
                    ForEach(card.details) { block in
                        VStack(alignment: .leading, spacing: 8) {
                            Text(block.heading)
                                .font(.system(.subheadline, design: .default).weight(.semibold))
                                .foregroundStyle(ink)
                            if let paragraph = block.paragraph, !paragraph.isEmpty {
                                Text(paragraph)
                                    .font(.system(.footnote, design: .default))
                                    .foregroundStyle(inkSecondary)
                                    .lineSpacing(3)
                            }
                            ForEach(block.bulletItems, id: \.self) { item in
                                HStack(alignment: .top, spacing: 8) {
                                    Text("–")
                                        .foregroundStyle(inkSecondary)
                                    Text(item)
                                        .font(.system(.footnote, design: .default))
                                        .foregroundStyle(inkSecondary)
                                }
                            }
                        }
                    }
                }
            }
        }
        .padding(isSpotlight ? 16 : 0)
        .background(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .fill(isSpotlight ? panel : Color.clear)
        )
    }

    @ViewBuilder
    private var mediaHero: some View {
        Group {
            if let imageURL {
                AsyncImage(url: imageURL) { phase in
                    switch phase {
                    case .success(let image):
                        image
                            .resizable()
                            .scaledToFill()
                    case .failure:
                        photoPlaceholder
                    default:
                        panel.overlay(ProgressView().tint(inkSecondary))
                    }
                }
            } else {
                photoPlaceholder
            }
        }
        .frame(maxWidth: .infinity)
        .frame(height: 220)
        .clipped()
        .clipShape(RoundedRectangle(cornerRadius: 22, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 22, style: .continuous)
                .stroke(hairline, lineWidth: 1)
        )
        .accessibilityHidden(true)
    }

    private var photoPlaceholder: some View {
        LinearGradient(
            colors: [
                colorScheme == .dark ? Color(white: 0.14) : Color(white: 0.92),
                colorScheme == .dark ? Color(white: 0.08) : Color(white: 0.86)
            ],
            startPoint: .topLeading,
            endPoint: .bottomTrailing
        )
    }

    private var badgeLabel: String? {
        if card.is_new { return catalog.badges.new }
        switch card.badgeKind {
        case .popular: return catalog.badges.popular
        case .premium: return catalog.badges.premium
        case .new: return catalog.badges.new
        case .none: return nil
        }
    }
}
