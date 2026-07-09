import Foundation

struct PAXDocumentItem: Codable, Identifiable, Hashable {
    let id: String
    var name: String
    var category: DocumentCategory
    var sizeLabel: String
    var modifiedAt: Date
    var detail: String

    enum DocumentCategory: String, Codable, CaseIterable, Identifiable {
        case contracts, invoices, guides, media, other

        var id: String { rawValue }

        var title: String {
            switch self {
            case .contracts: return L10n.FilesCategoryContracts
            case .invoices: return L10n.FilesCategoryInvoices
            case .guides: return L10n.FilesCategoryGuides
            case .media: return L10n.FilesCategoryMedia
            case .other: return L10n.FilesCategoryOther
            }
        }

        var systemImage: String {
            switch self {
            case .contracts: return "doc.text.fill"
            case .invoices: return "eurosign.circle.fill"
            case .guides: return "book.fill"
            case .media: return "photo.fill"
            case .other: return "folder.fill"
            }
        }
    }

    init(name: String, category: DocumentCategory, sizeLabel: String, detail: String = "") {
        self.id = UUID().uuidString
        self.name = name
        self.category = category
        self.sizeLabel = sizeLabel
        self.modifiedAt = Date()
        self.detail = detail
    }
}

@MainActor
final class FileLibraryStore: ObservableObject {
    static let shared = FileLibraryStore()

    @Published private(set) var documents: [PAXDocumentItem] = []

    private let storageKey = "pax.files.library"

    private init() {
        load()
        if documents.isEmpty { seedDefaults() }
    }

    func documents(in category: PAXDocumentItem.DocumentCategory) -> [PAXDocumentItem] {
        documents.filter { $0.category == category }.sorted { $0.modifiedAt > $1.modifiedAt }
    }

    func add(name: String, category: PAXDocumentItem.DocumentCategory, sizeLabel: String, detail: String = "") {
        let doc = PAXDocumentItem(name: name, category: category, sizeLabel: sizeLabel, detail: detail)
        documents.insert(doc, at: 0)
        persist()
        ActivityLogService.shared.log(
            category: L10n.ModuleFiles,
            title: L10n.ActivityFileAdded,
            detail: name,
            module: PlatformModule.files.rawValue,
            severity: .action
        )
    }

    func delete(_ document: PAXDocumentItem) {
        documents.removeAll { $0.id == document.id }
        persist()
    }

    private func seedDefaults() {
        documents = [
            PAXDocumentItem(name: "Service Agreement Template.pdf", category: .contracts, sizeLabel: "248 KB", detail: L10n.FilesSampleContract),
            PAXDocumentItem(name: "Live Chat Onboarding.pdf", category: .guides, sizeLabel: "1.2 MB", detail: L10n.FilesSampleGuide),
            PAXDocumentItem(name: "Q3 Invoice Summary.xlsx", category: .invoices, sizeLabel: "84 KB", detail: L10n.FilesSampleInvoice),
            PAXDocumentItem(name: "Brand Assets.zip", category: .media, sizeLabel: "4.8 MB", detail: L10n.FilesSampleMedia)
        ]
        persist()
    }

    private func load() {
        guard let data = UserDefaults.standard.data(forKey: storageKey),
              let decoded = try? JSONDecoder().decode([PAXDocumentItem].self, from: data) else { return }
        documents = decoded
    }

    private func persist() {
        guard let data = try? JSONEncoder().encode(documents) else { return }
        UserDefaults.standard.set(data, forKey: storageKey)
    }
}
