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
        var id: Int { abs(body.hashValue) }
        let body: String
        let created_at: String
        let author_user_id: Int
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
        var id: Int { abs(summary.hashValue) }
        let event_type: String
        let summary: String
        let created_at: String
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
}

struct CustomerOrderDetail: Decodable {
    struct Note: Decodable, Identifiable {
        var id: Int { abs(body.hashValue) }
        let body: String
        let created_at: String
    }
    struct Activity: Decodable, Identifiable {
        var id: Int { abs(summary.hashValue) }
        let summary: String
        let event_type: String
        let created_at: String
    }
    struct Assigned: Decodable {
        let user_id: Int
        let display_name: String
    }
    struct FileItem: Decodable, Identifiable {
        let id: Int
        let file_name: String
        let mime_type: String
        let file_size: Int
        let kind: String
        let created_at: String
        let download_url: String?
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
