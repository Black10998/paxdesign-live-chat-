import SwiftUI

struct RootView: View {
    @EnvironmentObject private var auth: CustomerAuthStore

    var body: some View {
        Group {
            if auth.isAuthenticated {
                CustomerTabView()
            } else {
                CustomerLoginView()
            }
        }
        .tint(Color(red: 0.21, green: 0.21, blue: 0.21))
    }
}

struct CustomerTabView: View {
    var body: some View {
        TabView {
            CustomerDashboardView()
                .tabItem { Label(String(localized: "Home"), systemImage: "house.fill") }
            CustomerChatView()
                .tabItem { Label(String(localized: "Chat"), systemImage: "message.fill") }
            CustomerServicesView()
                .tabItem { Label(String(localized: "Services"), systemImage: "square.grid.2x2.fill") }
            CustomerProfileView()
                .tabItem { Label(String(localized: "Account"), systemImage: "person.crop.circle") }
        }
    }
}
