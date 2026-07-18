import Foundation
import SwiftUI

struct StaffOrderSummary: Decodable, Identifiable, Hashable {
    let id: Int
    let ref: String
    let service_label: String
    let status: String
    let description: String?
    let customer_name: String
    let customer_email: String
    let created_at: String?
}

struct StaffOrderDetail: Decodable, Identifiable {
    struct Note: Decodable, Identifiable {
        let id: Int
        let body: String
        let visibility: String?
        let created_at: String?

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            let bodyValue = try c.decode(String.self, forKey: .body)
            id = try c.decodeIfPresent(Int.self, forKey: .id) ?? abs(bodyValue.hashValue)
            body = bodyValue
            visibility = try c.decodeIfPresent(String.self, forKey: .visibility)
            created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
        }

        private enum CodingKeys: String, CodingKey {
            case id, body, visibility, created_at
        }
    }

    struct Activity: Decodable, Identifiable {
        let id: Int
        let summary: String
        let event_type: String
        let created_at: String?

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            let summaryValue = try c.decode(String.self, forKey: .summary)
            id = try c.decodeIfPresent(Int.self, forKey: .id) ?? abs(summaryValue.hashValue)
            summary = summaryValue
            event_type = try c.decodeIfPresent(String.self, forKey: .event_type) ?? ""
            created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
        }

        private enum CodingKeys: String, CodingKey {
            case id, summary, event_type, created_at
        }
    }

    struct Assigned: Decodable {
        let user_id: Int
        let display_name: String?
    }

    let id: Int
    let ref: String
    let service_label: String
    let status: String
    let description: String?
    let customer_name: String
    let customer_email: String
    let notes: [Note]?
    let activity: [Activity]?
    let assigned: Assigned?
    let created_at: String?
}

struct StaffOrdersListResponse: Decodable {
    let orders: [StaffOrderSummary]
}

@MainActor
final class StaffOrdersCoordinator: ObservableObject {
    static let shared = StaffOrdersCoordinator()

    @Published private(set) var orders: [StaffOrderSummary] = []
    @Published private(set) var isLoading = false
    @Published private(set) var errorMessage: String?
    @Published var pendingOrderId: Int?

    var unreadCount: Int {
        orders.filter { $0.status == "received" || $0.status == "pending" }.count
    }

    private init() {}

    func refresh(auth: AuthStore) async {
        guard let api = auth.api else { return }
        isLoading = orders.isEmpty
        errorMessage = nil
        defer { isLoading = false }
        do {
            orders = try await api.fetchStaffOrders().orders
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func openOrder(_ orderId: Int) {
        pendingOrderId = orderId
        NotificationCenter.default.post(name: .paxOpenStaffOrder, object: nil, userInfo: ["order_id": orderId])
    }

    func consumePendingOrderId() -> Int? {
        defer { pendingOrderId = nil }
        return pendingOrderId
    }
}

extension Notification.Name {
    static let paxOpenStaffOrder = Notification.Name("paxOpenStaffOrder")
}
