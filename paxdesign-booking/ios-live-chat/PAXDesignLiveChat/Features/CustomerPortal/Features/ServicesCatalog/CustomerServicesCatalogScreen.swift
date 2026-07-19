import SwiftUI

struct CustomerServicesCatalogScreen: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    @StateObject private var network = CustomerNetworkMonitor.shared

    @State private var catalog: CustomerServicesCatalogResponse?
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
            .background(theme.background.ignoresSafeArea())
            .navigationTitle(catalog?.title ?? String(localized: "Services"))
            .navigationBarTitleDisplayMode(.inline)
            .toolbarBackground(theme.background, for: .navigationBar)
            .customerPortalToolbar()
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
                LazyVStack(alignment: catalog.isRTL ? .trailing : .leading, spacing: 0) {
                    headerSection(catalog)
                    statementSection(catalog)
                    cardsSection(catalog, proxy: proxy)
                    processSection(catalog)
                }
                .padding(.vertical, 20)
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

    private func headerSection(_ catalog: CustomerServicesCatalogResponse) -> some View {
        VStack(spacing: 12) {
            Text(catalog.title)
                .font(.system(size: 32, weight: .heavy))
                .tracking(-0.5)
                .foregroundStyle(theme.textPrimary)
                .multilineTextAlignment(.center)
            Text(catalog.subtitle)
                .font(.title3)
                .foregroundStyle(theme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
        .padding(.horizontal, 20)
        .padding(.bottom, 30)
    }

    private func statementSection(_ catalog: CustomerServicesCatalogResponse) -> some View {
        Text(catalog.statement)
            .font(.body)
            .lineSpacing(4)
            .foregroundStyle(theme.textPrimary)
            .multilineTextAlignment(.center)
            .padding(24)
            .frame(maxWidth: 820)
            .background(theme.panel)
            .overlay(RoundedRectangle(cornerRadius: 16).stroke(theme.border))
            .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            .padding(.horizontal, 20)
            .padding(.bottom, 50)
    }

    private func cardsSection(_ catalog: CustomerServicesCatalogResponse, proxy: ScrollViewProxy) -> some View {
        LazyVStack(spacing: 32) {
            ForEach(catalog.cards) { card in
                ServiceCatalogCardView(
                    card: card,
                    catalog: catalog,
                    isExpanded: expandedCardIDs.contains(card.id),
                    isSpotlight: spotlightCardID == card.id,
                    onToggleDetails: { toggleExpanded(card.id) },
                    onBook: { openRequest(for: card) }
                )
                .id(card.id)

                if card.id == catalog.security_section.after_card_id {
                    securityBreakSection(catalog)
                }
            }
        }
        .padding(.horizontal, 20)
        .padding(.bottom, 80)
    }

    private func securityBreakSection(_ catalog: CustomerServicesCatalogResponse) -> some View {
        VStack(spacing: 12) {
            Divider().overlay(theme.border)
                .padding(.top, 48)
            Text(catalog.security_section.title)
                .font(.system(size: 28, weight: .heavy))
                .tracking(-0.5)
                .foregroundStyle(theme.textPrimary)
                .multilineTextAlignment(.center)
            Text(catalog.security_section.subtitle)
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .multilineTextAlignment(.center)
                .frame(maxWidth: 820)
        }
        .padding(.vertical, 12)
    }

    private func processSection(_ catalog: CustomerServicesCatalogResponse) -> some View {
        VStack(spacing: 50) {
            Divider().overlay(theme.border)
            Text(catalog.process_title)
                .font(.system(size: 28, weight: .heavy))
                .tracking(-0.5)
                .foregroundStyle(theme.textPrimary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 20)

            LazyVGrid(columns: [GridItem(.adaptive(minimum: 220), spacing: 24)], spacing: 24) {
                ForEach(Array(catalog.process_steps.enumerated()), id: \.offset) { index, step in
                    processCard(number: index + 1, step: step)
                }
            }
            .padding(.horizontal, 20)
        }
        .padding(.top, 60)
        .padding(.bottom, 40)
    }

    private func processCard(number: Int, step: CustomerServicesCatalogResponse.ProcessStep) -> some View {
        VStack(spacing: 20) {
            Text("\(number)")
                .font(.system(size: 24, weight: .heavy))
                .foregroundStyle(Color.black)
                .frame(width: 50, height: 50)
                .background(theme.accent)
                .clipShape(Circle())
                .shadow(color: theme.accent.opacity(0.25), radius: 6)
            Text(step.title)
                .font(.title3.weight(.bold))
                .foregroundStyle(theme.textPrimary)
                .multilineTextAlignment(.center)
            Text(step.text)
                .font(.subheadline)
                .foregroundStyle(theme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .padding(.vertical, 30)
        .padding(.horizontal, 20)
        .frame(maxWidth: .infinity)
        .background(theme.cardBackground)
        .overlay(RoundedRectangle(cornerRadius: 16).stroke(theme.border))
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .shadow(color: theme.shadowDark, radius: 8, x: 8, y: 8)
        .shadow(color: theme.shadowLight, radius: 8, x: -8, y: -8)
    }

    private func servicesErrorView(_ message: String) -> some View {
        VStack(spacing: 16) {
            PAXIcon(network.isConnected ? "exclamationmark.triangle" : "wifi.slash", size: .display, tint: theme.accent)
            Text(message)
                .multilineTextAlignment(.center)
                .foregroundStyle(theme.textSecondary)
            Button(String(localized: "Try again")) { Task { await load(force: true) } }
                .buttonStyle(.borderedProminent)
                .tint(theme.accent)
        }
        .padding(32)
    }

    private func toggleExpanded(_ id: String) {
        withAnimation(.easeInOut(duration: 0.35)) {
            if expandedCardIDs.contains(id) {
                expandedCardIDs.remove(id)
            } else {
                expandedCardIDs.insert(id)
            }
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
            withAnimation(.easeInOut(duration: 0.5)) {
                proxy.scrollTo(id, anchor: .center)
            }
        } else {
            proxy.scrollTo(id, anchor: .center)
        }
        DispatchQueue.main.asyncAfter(deadline: .now() + 2.4) {
            if spotlightCardID == id { spotlightCardID = nil }
        }
    }

    private func load(force: Bool) async {
        if catalog == nil || force { isLoading = true }
        error = nil
        do {
            catalog = try await api.fetchServicesCatalog(lang: language.rawValue)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct ServiceCatalogCardView: View {
    @Environment(\.marketingTheme) private var theme
    let card: CustomerServicesCatalogResponse.Card
    let catalog: CustomerServicesCatalogResponse
    let isExpanded: Bool
    let isSpotlight: Bool
    let onToggleDetails: () -> Void
    let onBook: () -> Void

    var body: some View {
        ZStack(alignment: catalog.isRTL ? .topLeading : .topTrailing) {
            ServicesNeumorphicCard(highlighted: card.highlighted || isSpotlight) {
                VStack(alignment: catalog.isRTL ? .trailing : .leading, spacing: 0) {
                    VStack(alignment: catalog.isRTL ? .trailing : .leading, spacing: 18) {
                        Text(card.title)
                            .font(.system(size: 23, weight: .bold))
                            .foregroundStyle(theme.textPrimary)
                            .padding(catalog.isRTL ? .leading : .trailing, badgePadding)

                        Text(card.description)
                            .font(.subheadline)
                            .foregroundStyle(theme.textSecondary)
                            .lineSpacing(4)
                            .multilineTextAlignment(catalog.isRTL ? .trailing : .leading)

                        VStack(alignment: catalog.isRTL ? .trailing : .leading, spacing: 12) {
                            ForEach(card.features, id: \.self) { feature in
                                HStack(spacing: 12) {
                                    if catalog.isRTL {
                                        Text(feature)
                                            .font(.subheadline)
                                            .foregroundStyle(theme.textSecondary)
                                        ServicesRotatingDisc()
                                    } else {
                                        ServicesRotatingDisc()
                                        Text(feature)
                                            .font(.subheadline)
                                            .foregroundStyle(theme.textSecondary)
                                    }
                                }
                            }
                        }

                        ServicesInsetButton(title: catalog.book_label, action: onBook)

                        Button(action: onToggleDetails) {
                            Text(isExpanded ? catalog.less_label : catalog.more_label)
                                .font(.subheadline)
                                .underline()
                                .foregroundStyle(theme.linkBlue)
                                .frame(maxWidth: .infinity)
                        }
                        .buttonStyle(.plain)
                    }
                    .padding(24)

                    if isExpanded, !card.details.isEmpty {
                        VStack(alignment: catalog.isRTL ? .trailing : .leading, spacing: 16) {
                            Divider().overlay(theme.border)
                            ForEach(card.details) { block in
                                VStack(alignment: catalog.isRTL ? .trailing : .leading, spacing: 8) {
                                    Text(block.heading)
                                        .font(.subheadline.weight(.bold))
                                        .foregroundStyle(theme.textPrimary)
                                    if let paragraph = block.paragraph, !paragraph.isEmpty {
                                        Text(paragraph)
                                            .font(.footnote)
                                            .foregroundStyle(theme.textSecondary)
                                            .lineSpacing(3)
                                    }
                                    ForEach(block.bulletItems, id: \.self) { item in
                                        HStack(alignment: .top, spacing: 8) {
                                            if !catalog.isRTL {
                                                Text(String(localized: "–"))
                                                    .foregroundStyle(theme.accent)
                                            }
                                            Text(item)
                                                .font(.footnote)
                                                .foregroundStyle(theme.textSecondary)
                                            if catalog.isRTL {
                                                Text(String(localized: "–"))
                                                    .foregroundStyle(theme.accent)
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        .padding(.horizontal, 24)
                        .padding(.bottom, 24)
                        .transition(.opacity.combined(with: .move(edge: .top)))
                    }
                }
            }

            if card.is_new {
                ServicesCornerRibbon(label: catalog.badges.new, isRTL: catalog.isRTL)
            } else if card.badgeKind == .popular || card.badgeKind == .premium {
                Text(card.badgeKind == .popular ? catalog.badges.popular : catalog.badges.premium)
                    .font(.system(size: 11, weight: .heavy))
                    .textCase(.uppercase)
                    .tracking(0.5)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(theme.accent)
                    .foregroundStyle(Color.black)
                    .clipShape(Capsule())
                    .padding(16)
            }
        }
    }

    private var badgePadding: CGFloat {
        card.is_new || card.badgeKind != .none ? 72 : 0
    }
}
