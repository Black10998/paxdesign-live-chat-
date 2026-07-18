import SwiftUI

struct CustomerAboutView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @Environment(\.marketingTheme) private var theme
    @State private var about: CustomerAboutResponse?
    @State private var language: CustomerServicesCatalogLanguage = {
        let code = Locale.current.language.languageCode?.identifier ?? "de"
        return CustomerServicesCatalogLanguage(rawValue: code) ?? .de
    }()
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        ScrollView {
            if let about {
                LazyVStack(spacing: 32) {
                    heroSection(about.hero)
                    introSection(about.intro)
                    valuesSection(about.values)
                    statsSection(about.stats)
                    awardsSection(about.awards)
                    if let gallery = about.gallery, !gallery.isEmpty {
                        gallerySection(gallery)
                    }
                }
                .padding(.vertical)
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load"), systemImage: "exclamationmark.triangle", description: Text(error))
                    .padding(.top, 40)
            } else if isLoading {
                CustomerAboutSkeleton()
                    .padding(.top, 8)
            }
        }
        .background(theme.background.ignoresSafeArea())
        .navigationTitle(about?.hero.title ?? String(localized: "About"))
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(theme.background, for: .navigationBar)
        .refreshable { await load(force: true) }
        .task(id: language.rawValue) { await load(force: false) }
        .environment(\.layoutDirection, about?.isRTL == true ? .rightToLeft : .leftToRight)
    }

    private func heroSection(_ hero: CustomerAboutResponse.Hero) -> some View {
        VStack(spacing: 10) {
            Text(hero.title)
                .font(.title2.weight(.bold))
                .foregroundStyle(theme.textPrimary)
                .multilineTextAlignment(.center)
            if !hero.subtitle.isEmpty {
                Text(hero.subtitle)
                    .font(.body)
                    .foregroundStyle(theme.textSecondary)
                    .multilineTextAlignment(.center)
            }
        }
        .padding(.horizontal, 20)
    }

    private func introSection(_ intro: CustomerAboutResponse.Intro) -> some View {
        VStack(spacing: 12) {
            Text(intro.heading)
                .font(.title3.weight(.semibold))
                .foregroundStyle(theme.textSecondary)
            Text(intro.brand)
                .font(.system(size: 32, weight: .heavy))
                .multilineTextAlignment(.center)
                .foregroundStyle(theme.textPrimary)
            HStack(spacing: 8) {
                Text(intro.since_label)
                    .foregroundStyle(theme.textSecondary)
                Text(intro.since)
                    .foregroundStyle(theme.accent)
                    .fontWeight(.bold)
            }
            Text(intro.about_label)
                .font(.headline)
                .foregroundStyle(theme.textPrimary)
            Text(intro.about_text)
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .padding(24)
        .frame(maxWidth: .infinity)
        .background(theme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .padding(.horizontal, 20)
    }

    private func valuesSection(_ values: CustomerAboutResponse.Values) -> some View {
        VStack(alignment: .leading, spacing: 16) {
            Text(values.title)
                .font(.title3.weight(.bold))
                .foregroundStyle(theme.textPrimary)
                .padding(.horizontal, 20)

            ForEach(values.items) { item in
                VStack(alignment: .leading, spacing: 8) {
                    Text(item.title)
                        .font(.headline)
                        .foregroundStyle(theme.textPrimary)
                    Text(item.text)
                        .font(.subheadline)
                        .foregroundStyle(theme.textSecondary)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .padding(20)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(theme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                .padding(.horizontal, 20)
            }
        }
    }

    private func statsSection(_ stats: [CustomerAboutResponse.Stat]) -> some View {
        HStack(spacing: 12) {
            ForEach(stats) { stat in
                VStack(spacing: 6) {
                    Text("\(stat.value)\(stat.suffix)")
                        .font(.title.weight(.heavy))
                        .foregroundStyle(theme.accent)
                    Text(stat.label)
                        .font(.caption)
                        .foregroundStyle(theme.textSecondary)
                        .multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity)
                .padding(.vertical, 20)
                .background(theme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            }
        }
        .padding(.horizontal, 20)
    }

    private func awardsSection(_ awards: CustomerAboutResponse.Awards) -> some View {
        VStack(spacing: 16) {
            Text(awards.title)
                .font(.title3.weight(.bold))
                .foregroundStyle(theme.textPrimary)
                .multilineTextAlignment(.center)
            Text(awards.text)
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .multilineTextAlignment(.center)
            Text(String(localized: "★★★★★"))
                .font(.title3)
                .foregroundStyle(theme.accent)
                .accessibilityLabel(String(localized: "Five star rating"))
            Text(awards.rating_label)
                .font(.headline)
                .foregroundStyle(theme.textPrimary)
        }
        .padding(24)
        .frame(maxWidth: .infinity)
        .background(theme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .padding(.horizontal, 20)
    }

    private func gallerySection(_ urls: [String]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(String(localized: "Gallery"))
                .font(.title3.weight(.bold))
                .foregroundStyle(theme.textPrimary)
                .padding(.horizontal, 20)

            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 12) {
                    ForEach(urls, id: \.self) { urlString in
                        if let url = URL(string: urlString) {
                            AsyncImage(url: url) { phase in
                                if case .success(let image) = phase {
                                    image.resizable().scaledToFill()
                                } else {
                                    Color.gray.opacity(0.15)
                                }
                            }
                            .frame(width: 200, height: 140)
                            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                        }
                    }
                }
                .padding(.horizontal, 20)
            }
        }
    }

    private func load(force: Bool) async {
        if about == nil || force { isLoading = true }
        error = nil
        do {
            about = try await api.fetchAbout(lang: language.rawValue)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

struct CustomerContactView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    @State private var contact: CustomerContactResponse?
    @State private var language: CustomerServicesCatalogLanguage = {
        let code = Locale.current.language.languageCode?.identifier ?? "de"
        return CustomerServicesCatalogLanguage(rawValue: code) ?? .de
    }()
    @State private var error: String?
    @State private var isLoading = true
    @State private var showRequestSheet = false

    var body: some View {
        ScrollView {
            if let contact {
                VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                    contactHero(contact.hero)
                    contactMethods(contact.contact)
                    faqSection(contact.faq)
                    actionButtons(contact.cta)
                }
                .padding(.vertical)
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load"), systemImage: "exclamationmark.triangle", description: Text(error))
                    .padding(.top, 40)
            } else if isLoading {
                CustomerContactSkeleton()
                    .padding(.top, 8)
            }
        }
        .background(theme.background.ignoresSafeArea())
        .navigationTitle(String(localized: "Contact"))
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(theme.background, for: .navigationBar)
        .refreshable { await load(force: true) }
        .task(id: language.rawValue) { await load(force: false) }
        .sheet(isPresented: $showRequestSheet) {
            NavigationStack {
                CustomerCreateOrderView()
                    .environmentObject(api)
            }
        }
        .environment(\.layoutDirection, contact?.isRTL == true ? .rightToLeft : .leftToRight)
    }

    private func contactHero(_ hero: CustomerContactResponse.Hero) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(hero.title)
                .font(.title2.weight(.bold))
                .foregroundStyle(theme.textPrimary)
            Text(hero.subtitle)
                .font(.body)
                .foregroundStyle(theme.textSecondary)
        }
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(theme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .padding(.horizontal)
    }

    private func contactMethods(_ info: CustomerContactResponse.ContactInfo) -> some View {
        VStack(spacing: 12) {
            contactRow(icon: "phone.fill", title: String(localized: "Phone"), value: info.phone) {
                if let url = URL(string: "tel:\(info.phone.filter { $0.isNumber || $0 == "+" })") {
                    UIApplication.shared.open(url)
                }
            }
            contactRow(icon: "envelope.fill", title: String(localized: "Email"), value: info.email) {
                if let url = URL(string: "mailto:\(info.email)") {
                    UIApplication.shared.open(url)
                }
            }
            contactRow(icon: "mappin.and.ellipse", title: String(localized: "Address"), value: info.address) {
                let encoded = info.address.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? info.address
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
                    .foregroundStyle(theme.accent)
                    .frame(width: 28)
                VStack(alignment: .leading, spacing: 4) {
                    Text(title)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(theme.textSecondary)
                    Text(value)
                        .font(.body)
                        .foregroundStyle(theme.textPrimary)
                        .multilineTextAlignment(.leading)
                }
                Spacer()
                Image(systemName: "chevron.right")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            .padding(16)
            .background(theme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        }
        .buttonStyle(.plain)
    }

    private func faqSection(_ items: [CustomerContactResponse.FAQItem]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(String(localized: "FAQ"))
                .font(.title3.weight(.bold))
                .foregroundStyle(theme.textPrimary)
                .padding(.horizontal)

            ForEach(items) { item in
                DisclosureGroup {
                    Text(item.answer)
                        .font(.subheadline)
                        .foregroundStyle(theme.textSecondary)
                        .padding(.top, 8)
                } label: {
                    Text(item.question)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(theme.textPrimary)
                }
                .padding(16)
                .background(theme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                .padding(.horizontal)
            }
        }
    }

    private func actionButtons(_ cta: CustomerContactResponse.CTA) -> some View {
        VStack(spacing: 12) {
            Button(cta.primary) { showRequestSheet = true }
                .buttonStyle(HomepageContactPrimaryButtonStyle())
            Button(cta.secondary) { navigation.openChat() }
                .buttonStyle(HomepageContactSecondaryButtonStyle())
        }
        .padding(.horizontal)
        .padding(.top, 8)
    }

    private func load(force: Bool) async {
        if contact == nil || force { isLoading = true }
        error = nil
        do {
            contact = try await api.fetchContact(lang: language.rawValue)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct HomepageContactPrimaryButtonStyle: ButtonStyle {
    @Environment(\.marketingTheme) private var theme

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .foregroundStyle(.black)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 14)
            .background(theme.accent.opacity(configuration.isPressed ? 0.85 : 1))
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}

private struct HomepageContactSecondaryButtonStyle: ButtonStyle {
    @Environment(\.marketingTheme) private var theme

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .foregroundStyle(theme.textPrimary)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 14)
            .background(theme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}
