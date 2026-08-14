import Foundation

struct CustomerCybercrimeUpload: Identifiable {
    let id: UUID
    let field: String
    let filename: String
    let mime: String
    let data: Data

    init(field: String, filename: String, mime: String, data: Data) {
        self.id = UUID()
        self.field = field
        self.filename = filename
        self.mime = mime
        self.data = data
    }
}

struct CustomerCybercrimeListResponse: Decodable {
    let reports: [CustomerCybercrimeReport]
    let active: CustomerCybercrimeReport?
    let history: [CustomerCybercrimeReport]?
}

struct CustomerCybercrimeActiveResponse: Decodable {
    let active: Bool
    let report: CustomerCybercrimeReport?
}

struct CustomerCybercrimeDetailResponse: Decodable {
    let report: CustomerCybercrimeReport?
    let message_id: Int?
    let ok: Bool?
}

struct CustomerCybercrimeSubmitResponse: Decodable {
    let referenceId: String?
    let message: String?
    let report: CustomerCybercrimeReport?

    var reference: String {
        if let referenceId, !referenceId.isEmpty { return referenceId }
        return report?.reference_id ?? ""
    }
}

struct CustomerCybercrimeReport: Decodable, Identifiable, Hashable {
    var id: String { reference_id }
    let reference_id: String
    let status: String?
    let status_label: String?
    let customer_status: String?
    let is_active: Bool?
    let category: String?
    let category_label: String?
    let urgency: String?
    let reporter_name: String?
    let reporter_email: String?
    let incident_at: String?
    let created_at: String?
    let updated_at: String?
    let description: String?
    let platforms: String?
    let financial_loss: String?
    let financial_currency: String?
    let attachments: [CustomerCybercrimeAttachment]?
    let timeline: [CustomerCybercrimeTimelineEntry]?
    let unread_count: Int?
    let chat_session_id: String?

    var isOpen: Bool { is_active ?? CustomerCybercrimeCatalog.isActiveStatus(status) }

    var displayStatus: String {
        if let status_label, !status_label.isEmpty { return status_label }
        return CustomerCybercrimeCatalog.statusTitle(status)
    }

    var displayCategory: String {
        if let category_label, !category_label.isEmpty { return category_label }
        return CustomerCybercrimeCatalog.categoryTitle(category)
    }
}

struct CustomerCybercrimeAttachment: Decodable, Identifiable, Hashable {
    var id: String { url ?? name ?? UUID().uuidString }
    let field: String?
    let name: String?
    let url: String?
    let type: String?
    let size: String?
}

struct CustomerCybercrimeTimelineEntry: Decodable, Identifiable, Hashable {
    var id: Int { identifier }
    let identifier: Int
    let author_type: String?
    let channel: String?
    let body: String?
    let created_at: String?
    let status: String?
    let status_label: String?
    let sender_key: String?
    let customer_name: String?

    enum CodingKeys: String, CodingKey {
        case identifier = "id"
        case author_type, channel, body, created_at, status, status_label, sender_key, customer_name
    }

    var isCustomer: Bool { author_type == "customer" || sender_key == "customer" }
    var isStaff: Bool { author_type == "staff" || sender_key == "support" }
}
