import SwiftUI

struct StaffOrdersListView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var coordinator = StaffOrdersCoordinator.shared
    @State private var deepLinkOrder: StaffOrderNavigationTarget?

    private struct StaffOrderNavigationTarget: Identifiable, Hashable {
        let id: Int
    }

    var body: some View {
        Group {
            if coordinator.isLoading && coordinator.orders.isEmpty {
                PAXScreenLoadingStack(status: String(localized: "Loading orders…"), rowCount: 4, preset: .list)
                    .padding(.horizontal, 16)
            } else if let error = coordinator.errorMessage, coordinator.orders.isEmpty {
                PAXContentUnavailableView(
                    String(localized: "Unable to load orders"),
                    systemImage: "exclamationmark.triangle",
                    description: Text(error)
                ) {
                    Button(String(localized: "Try again")) {
                        Task { await coordinator.refresh(auth: auth) }
                    }
                }
            } else if coordinator.orders.isEmpty {
                PAXContentUnavailableView(
                    String(localized: "No customer requests yet"),
                    systemImage: "tray",
                    description: Text(String(localized: "New service requests from customers will appear here."))
                )
            } else {
                ScrollView {
                    LazyVStack(spacing: 12) {
                        ForEach(coordinator.orders) { order in
                            NavigationLink(value: order.id) {
                                StaffOrderCard(order: order)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
            }
        }
        .paxScreenBackground()
        .navigationTitle(String(localized: "Orders & Requests"))
        .navigationDestination(for: Int.self) { orderId in
            StaffOrderDetailView(orderId: orderId)
        }
        .toolbar {
            if coordinator.unreadCount > 0 {
                ToolbarItem(placement: .topBarTrailing) {
                    Text("\(coordinator.unreadCount)")
                        .font(.caption.weight(.bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(Color.red)
                        .clipShape(Capsule())
                }
            }
        }
        .refreshable { await coordinator.refresh(auth: auth) }
        .task { await coordinator.refresh(auth: auth) }
        .onReceive(NotificationCenter.default.publisher(for: .paxOpenStaffOrder)) { note in
            if let orderId = note.userInfo?["order_id"] as? Int {
                deepLinkOrder = StaffOrderNavigationTarget(id: orderId)
            }
        }
        .navigationDestination(item: $deepLinkOrder) { target in
            StaffOrderDetailView(orderId: target.id)
        }
    }
}

private struct StaffOrderCard: View {
    let order: StaffOrderSummary

    private var isUnread: Bool {
        order.status == "received" || order.status == "pending"
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(alignment: .top, spacing: 12) {
                ZStack {
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(PAXTheme.accent.opacity(0.14))
                        .frame(width: 44, height: 44)
                    PAXIcon("doc.text.fill", size: .row, emphasis: .primary)
                }
                VStack(alignment: .leading, spacing: 4) {
                    Text(order.service_label)
                        .font(.headline)
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(order.customer_name.isEmpty ? order.customer_email : order.customer_name)
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer(minLength: 8)
                if isUnread {
                    Circle()
                        .fill(Color.red)
                        .frame(width: 10, height: 10)
                        .accessibilityLabel(String(localized: "Unread"))
                }
            }

            HStack {
                Text(order.ref)
                    .font(.caption.monospaced())
                    .foregroundStyle(PAXTheme.textTertiary)
                Spacer()
                Text(order.status.capitalized)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.accent)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 4)
                    .background(PAXTheme.accent.opacity(0.12))
                    .clipShape(Capsule())
            }
        }
        .padding(16)
        .paxCard(.list, tint: isUnread ? PAXTheme.accent : PAXTheme.textSecondary)
    }
}

private struct StaffOrderRow: View {
    let order: StaffOrderSummary

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack {
                Text(order.service_label)
                    .font(.headline)
                Spacer()
                if order.status == "received" || order.status == "pending" {
                    Circle()
                        .fill(Color.red)
                        .frame(width: 8, height: 8)
                }
            }
            Text(order.customer_name.isEmpty ? order.customer_email : order.customer_name)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
            HStack {
                Text(order.ref)
                    .font(.caption.monospaced())
                Spacer()
                Text(order.status.capitalized)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.accent)
            }
        }
        .padding(.vertical, 4)
    }
}

struct StaffOrderDetailView: View {
    @EnvironmentObject private var auth: AuthStore
    let orderId: Int
    @State private var order: StaffOrderDetail?
    @State private var error: String?
    @State private var statusDraft = ""
    @State private var noteDraft = ""
    @State private var isSaving = false

    var body: some View {
        Group {
            if let order {
                Form {
                    Section(String(localized: "Customer")) {
                        LabeledContent(String(localized: "Name"), value: order.customer_name)
                        LabeledContent(String(localized: "Email"), value: order.customer_email)
                    }
                    Section(String(localized: "Request")) {
                        LabeledContent(String(localized: "Reference"), value: order.ref)
                        LabeledContent(String(localized: "Service"), value: order.service_label)
                        LabeledContent(String(localized: "Status"), value: order.status)
                        if let description = order.description, !description.isEmpty {
                            Text(description)
                        }
                    }
                    if let assigned = order.assigned, assigned.user_id > 0 {
                        Section(String(localized: "Assigned")) {
                            Text(assigned.display_name ?? String(localized: "Staff member"))
                        }
                    }
                    if let notes = order.notes, !notes.isEmpty {
                        Section(String(localized: "Notes")) {
                            ForEach(notes) { note in
                                VStack(alignment: .leading, spacing: 4) {
                                    if note.visibility == "internal" {
                                        Text(String(localized: "Internal"))
                                            .font(.caption2.weight(.semibold))
                                            .foregroundStyle(.orange)
                                    }
                                    Text(note.body)
                                }
                            }
                        }
                    }
                    if let activity = order.activity, !activity.isEmpty {
                        Section(String(localized: "Activity")) {
                            ForEach(activity) { item in
                                Text(item.summary)
                            }
                        }
                    }
                    if auth.canReplyChats {
                        Section(String(localized: "Update")) {
                            TextField(String(localized: "Status"), text: $statusDraft)
                            TextField(String(localized: "Add note for customer"), text: $noteDraft, axis: .vertical)
                            Button(isSaving ? String(localized: "Saving…") : String(localized: "Save changes")) {
                                Task { await save() }
                            }
                            .disabled(isSaving)
                        }
                    }
                }
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load order"), systemImage: "exclamationmark.triangle", description: Text(error))
            } else {
                ProgressView()
            }
        }
        .navigationTitle(order?.service_label ?? String(localized: "Order"))
        .task { await load() }
    }

    private func load() async {
        guard let api = auth.api else { return }
        do {
            let detail = try await api.fetchStaffOrder(id: orderId)
            order = detail
            statusDraft = detail.status
        } catch {
            self.error = error.localizedDescription
        }
    }

    private func save() async {
        guard let api = auth.api else { return }
        isSaving = true
        defer { isSaving = false }
        do {
            _ = try await api.updateStaffOrder(
                id: orderId,
                status: statusDraft.isEmpty ? nil : statusDraft,
                note: noteDraft.isEmpty ? nil : noteDraft
            )
            noteDraft = ""
            await load()
            await StaffOrdersCoordinator.shared.refresh(auth: auth)
        } catch {
            self.error = error.localizedDescription
        }
    }
}
