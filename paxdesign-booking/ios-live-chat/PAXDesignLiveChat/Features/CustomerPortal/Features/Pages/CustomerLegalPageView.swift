import SwiftUI

struct CustomerLegalPageView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    let title: String

    @State private var page: CustomerLegalPageResponse?
    @State private var error: String?
    @State private var isLoading = true
    @State private var language: CustomerServicesCatalogLanguage = {
        let code = Locale.current.language.languageCode?.identifier ?? "de"
        return CustomerServicesCatalogLanguage(rawValue: code) ?? .de
    }()

    var body: some View {
        ScrollView {
            if let page {
                VStack(alignment: .leading, spacing: 20) {
                    if !page.subtitle.isEmpty {
                        Text(page.subtitle)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                    ForEach(page.sections) { section in
                        VStack(alignment: .leading, spacing: 8) {
                            Text(section.title)
                                .font(.headline)
                                .foregroundStyle(PAXTheme.textPrimary)
                            Text(section.body)
                                .font(.body)
                                .foregroundStyle(PAXTheme.textSecondary)
                                .fixedSize(horizontal: false, vertical: true)
                        }
                        .frame(maxWidth: .infinity, alignment: .leading)
                    }
                    if let url = URL(string: page.website_url) {
                        Link(destination: url) {
                            Label(
                                page.cta.isEmpty ? String(localized: "View on website") : page.cta,
                                systemImage: "safari"
                            )
                            .frame(maxWidth: .infinity)
                        }
                        .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                    }
                }
                .padding()
            } else if let error {
                PAXContentUnavailableView(
                    String(localized: "Unable to load"),
                    systemImage: "exclamationmark.triangle",
                    description: Text(error)
                )
                .padding(.top, 40)
            } else if isLoading {
                CustomerFormSkeleton()
                    .padding(.top, 8)
            }
        }
        .background(PAXBackground())
        .navigationTitle(page?.title ?? title)
        .navigationBarTitleDisplayMode(.inline)
        .environment(\.layoutDirection, page?.isRTL == true ? .rightToLeft : .leftToRight)
        .task(id: language.rawValue) { await load() }
        .refreshable { await load(force: true) }
    }

    private func load(force: Bool = false) async {
        if page == nil || force { isLoading = true }
        error = nil
        do {
            page = try await api.fetchLegalPage(slug: slug, lang: language.rawValue)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}
