import Foundation

struct CustomerProfileResponse: Decodable {
    struct Profile: Decodable {
        let id: Int
        let display_name: String
        let email: String
        let verified: Bool
        let role: String
        let avatar_url: String?
    }
    let profile: Profile
}

struct CustomerSettingsResponse: Decodable {
    struct NotificationPrefs: Codable {
        var chat: Bool
        var project: Bool
        var order: Bool
        var news: Bool
        var security: Bool
        var push_enabled: Bool
    }
    let notifications: NotificationPrefs
}

struct CustomerProjectSummary: Decodable, Identifiable {
    let id: Int
    let ref: String
    let title: String
    let status: String
    let progress: Int
    let description: String?
}

struct CustomerProjectDetail: Decodable {
    struct Milestone: Decodable, Identifiable {
        var id: Int { sort_order }
        let title: String
        let description: String?
        let status: String
        let sort_order: Int
        let due_date: String?
        let completed_at: String?
    }
    struct Note: Decodable, Identifiable {
        let id: Int
        let body: String
        let created_at: String
        let author_user_id: Int

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            id = try c.decodeIfPresent(Int.self, forKey: .id) ?? abs((try c.decode(String.self, forKey: .body)).hashValue)
            body = try c.decode(String.self, forKey: .body)
            created_at = try c.decodeIfPresent(String.self, forKey: .created_at) ?? ""
            author_user_id = try c.decodeIfPresent(Int.self, forKey: .author_user_id) ?? 0
        }

        private enum CodingKeys: String, CodingKey {
            case id, body, created_at, author_user_id
        }
    }
    struct FileItem: Decodable, Identifiable {
        let id: Int
        let file_name: String
        let mime_type: String
        let file_size: Int
        let category: String
        let created_at: String
        let download_url: String?
    }
    struct Assignee: Decodable, Identifiable {
        var id: Int { user_id }
        let user_id: Int
        let role_label: String
        let display_name: String?
    }
    struct Activity: Decodable, Identifiable {
        let id: Int
        let event_type: String
        let summary: String
        let created_at: String

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            let summaryValue = try c.decode(String.self, forKey: .summary)
            let created = try c.decodeIfPresent(String.self, forKey: .created_at) ?? ""
            id = try c.decodeIfPresent(Int.self, forKey: .id) ?? abs("\(created)-\(summaryValue)".hashValue)
            event_type = try c.decodeIfPresent(String.self, forKey: .event_type) ?? ""
            summary = summaryValue
            created_at = created
        }

        private enum CodingKeys: String, CodingKey {
            case id, event_type, summary, created_at
        }
    }

    let id: Int
    let ref: String
    let title: String
    let description: String?
    let status: String
    let progress: Int
    let milestones: [Milestone]?
    let notes: [Note]?
    let files: [FileItem]?
    let assignees: [Assignee]?
    let activity: [Activity]?
}

struct CustomerProjectsResponse: Decodable {
    let projects: [CustomerProjectSummary]
}

struct CustomerOrderSummary: Decodable, Identifiable {
    let id: Int
    let ref: String
    let service_label: String
    let status: String
    let description: String?
    let created_at: String?

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        id = CustomerPortalDecode.int(c, .id)
        ref = CustomerPortalDecode.string(c, .ref)
        service_label = CustomerPortalDecode.string(c, .service_label)
        status = CustomerPortalDecode.string(c, .status)
        description = try c.decodeIfPresent(String.self, forKey: .description)
        created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
    }

    private enum CodingKeys: String, CodingKey {
        case id, ref, service_label, status, description, created_at
    }
}

struct CustomerOrderDetail: Decodable {
    struct Note: Decodable, Identifiable {
        let id: Int
        let body: String
        let created_at: String

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            let bodyValue = try c.decode(String.self, forKey: .body)
            let created = try c.decodeIfPresent(String.self, forKey: .created_at) ?? ""
            id = CustomerPortalDecode.int(c, .id)
            body = bodyValue
            created_at = created
        }

        private enum CodingKeys: String, CodingKey {
            case id, body, created_at
        }
    }
    struct Activity: Decodable, Identifiable {
        let id: Int
        let summary: String
        let event_type: String
        let created_at: String

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            let summaryValue = try c.decode(String.self, forKey: .summary)
            let created = try c.decodeIfPresent(String.self, forKey: .created_at) ?? ""
            let decodedID = CustomerPortalDecode.int(c, .id)
            id = decodedID != 0 ? decodedID : abs("\(created)-\(summaryValue)".hashValue)
            summary = summaryValue
            event_type = try c.decodeIfPresent(String.self, forKey: .event_type) ?? ""
            created_at = created
        }

        private enum CodingKeys: String, CodingKey {
            case id, summary, event_type, created_at
        }
    }
    struct Assigned: Decodable {
        let user_id: Int
        let display_name: String?

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            user_id = CustomerPortalDecode.int(c, .user_id)
            display_name = try c.decodeIfPresent(String.self, forKey: .display_name)
        }

        private enum CodingKeys: String, CodingKey {
            case user_id, display_name
        }

        var label: String {
            let name = display_name?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
            return name.isEmpty ? String(localized: "Unassigned") : name
        }
    }
    struct FileItem: Decodable, Identifiable {
        let id: Int
        let file_name: String
        let mime_type: String
        let file_size: Int
        let kind: String
        let created_at: String
        let download_url: String?

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            id = CustomerPortalDecode.int(c, .id)
            file_name = CustomerPortalDecode.string(c, .file_name)
            mime_type = CustomerPortalDecode.string(c, .mime_type)
            file_size = CustomerPortalDecode.int(c, .file_size)
            kind = CustomerPortalDecode.string(c, .kind)
            created_at = CustomerPortalDecode.string(c, .created_at)
            download_url = try c.decodeIfPresent(String.self, forKey: .download_url)
        }

        private enum CodingKeys: String, CodingKey {
            case id, file_name, mime_type, file_size, kind, created_at, download_url
        }
    }

    let id: Int
    let ref: String
    let service_label: String
    let status: String
    let description: String?
    let notes: [Note]?
    let activity: [Activity]?
    let assigned: Assigned?
    let files: [FileItem]?

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        id = CustomerPortalDecode.int(c, .id)
        ref = CustomerPortalDecode.string(c, .ref)
        service_label = CustomerPortalDecode.string(c, .service_label)
        status = CustomerPortalDecode.string(c, .status)
        description = try c.decodeIfPresent(String.self, forKey: .description)
        notes = try c.decodeIfPresent([Note].self, forKey: .notes)
        activity = try c.decodeIfPresent([Activity].self, forKey: .activity)
        assigned = try c.decodeIfPresent(Assigned.self, forKey: .assigned)
        files = try c.decodeIfPresent([FileItem].self, forKey: .files)
    }

    private enum CodingKeys: String, CodingKey {
        case id, ref, service_label, status, description, notes, activity, assigned, files
    }
}

struct CustomerOrdersResponse: Decodable {
    let orders: [CustomerOrderSummary]
}

struct CustomerNewsItem: Decodable, Identifiable {
    var id: String { slug }
    let slug: String
    let title: String
    let excerpt: String?
    let priority: String?
    let published_at: String?
    let body: String?
    let image_url: String?
}

struct CustomerNewsResponse: Decodable {
    let items: [CustomerNewsItem]
}

struct CustomerNotificationItem: Decodable, Identifiable {
    let id: Int
    let category: String
    let title: String
    let body: String?
    let deep_link: String?
    let is_read: Bool
    let created_at: String
}

struct CustomerNotificationsResponse: Decodable {
    let items: [CustomerNotificationItem]
    let unread_count: Int
}

struct CustomerFileLibraryItem: Decodable, Identifiable {
    let recordId: Int
    let source: String
    let parent_id: Int
    let parent_title: String
    let file_name: String
    let mime_type: String
    let file_size: Int
    let kind: String
    let created_at: String
    let download_url: String?

    var id: String { "\(source)-\(parent_id)-\(recordId)" }

    enum CodingKeys: String, CodingKey {
        case recordId = "id"
        case source, parent_id, parent_title, file_name, mime_type, file_size, kind, created_at, download_url
    }
}

struct CustomerFilesResponse: Decodable {
    let files: [CustomerFileLibraryItem]
}

struct CustomerPortfolioItem: Decodable, Identifiable {
    var id: String { slug }
    let slug: String
    let title: String
    let excerpt: String?
    let image_url: String?
    let client: String?
    let project_url: String?
    let published_at: String?
    let category_names: [String]?
    let category_slugs: [String]?
    let stats: [CustomerPortfolioStat]?

    var displayCategory: String {
        category_names?.first ?? ""
    }
}

struct CustomerPortfolioStat: Decodable, Identifiable {
    var id: String { value + label }
    let value: String
    let label: String
}

struct CustomerPortfolioDetail: Decodable {
    let slug: String
    let title: String
    let excerpt: String?
    let image_url: String?
    let client: String?
    let project_url: String?
    let published_at: String?
    let body: String?
    let gallery: [String]?
    let categories: [String]?
    let blocks: [CustomerContentBlock]?
    let structured: CustomerPortfolioStructuredDetail?
}

struct CustomerPortfolioResponse: Decodable {
    struct Category: Decodable {
        let slug: String
        let name: String
        let count: Int?
    }
    let categories: [Category]?
    let items: [CustomerPortfolioItem]
    let lang: String?
}

struct CustomerPortfolioShowcaseResponse: Decodable {
    struct Header: Decodable {
        let tags: [String]
        let title: String
        let intro: String
    }

    struct CTA: Decodable {
        let tags: [String]
        let title: String
        let text: String
        let button: String
        let url: String
    }

    let lang: String
    let dir: String
    let header: Header
    let cta: CTA
    let categories: [CustomerPortfolioResponse.Category]?
    let items: [CustomerPortfolioItem]

    var isRTL: Bool { dir == "rtl" }
}

struct CustomerServiceDetail: Decodable {
    let slug: String
    let name: String
    let category: String
    let description: String
    let body_html: String?
    let body_text: String?
    let features: [String]?
    let examples: [String]?
    let related: [String]?
    let image_url: String?
    let icon_key: String?
    let order_url: String?
    let featured: Bool
    let blocks: [CustomerContentBlock]?
}

struct CustomerContentNavigation: Decodable {
    let locale: String?
    let sections: [Section]

    struct Section: Decodable, Identifiable {
        var id: String { key }
        let key: String
        let title: String
        let items: [MenuItem]
    }

    struct MenuItem: Decodable, Identifiable {
        var id: String { "\(slug)-\(title)" }
        let title: String
        let slug: String
        let type: String
        let url: String?
        let children: [MenuItem]?
    }
}

struct CustomerContentPage: Decodable, Identifiable {
    var id: String { slug }
    let slug: String
    let title: String
    let excerpt: String?
    let image_url: String?
    let type: String?
    let updated_at: String?
    let body_html: String?
    let body_text: String?
    let gallery: [String]?
    let blocks: [CustomerContentBlock]?
}

struct CustomerContentBlock: Decodable {
    struct AccordionItem: Decodable {
        let title: String
        let text: String
    }

    let type: String
    let text: String?
    let html: String?
    let title: String?
    let level: Int?
    let url: String?
    let caption: String?
    let images: [String]?
    let listItems: [String]?
    let accordionItems: [AccordionItem]?
    let slug: String?
    let action: String?

    enum CodingKeys: String, CodingKey {
        case type, text, html, title, level, url, caption, images, items, slug, action
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        type = try container.decode(String.self, forKey: .type)
        text = try container.decodeIfPresent(String.self, forKey: .text)
        html = try container.decodeIfPresent(String.self, forKey: .html)
        title = try container.decodeIfPresent(String.self, forKey: .title)
        level = CustomerPortalDecode.optionalInt(container, .level)
        url = try container.decodeIfPresent(String.self, forKey: .url)
        caption = try container.decodeIfPresent(String.self, forKey: .caption)
        images = try container.decodeIfPresent([String].self, forKey: .images)
        slug = try container.decodeIfPresent(String.self, forKey: .slug)
        action = try container.decodeIfPresent(String.self, forKey: .action)
        if type == "accordion" {
            accordionItems = try container.decodeIfPresent([AccordionItem].self, forKey: .items)
            listItems = nil
        } else {
            listItems = try container.decodeIfPresent([String].self, forKey: .items)
            accordionItems = nil
        }
    }
}
