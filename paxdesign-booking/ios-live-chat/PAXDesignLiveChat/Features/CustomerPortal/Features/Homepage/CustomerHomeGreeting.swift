import Foundation

/// Time-of-day greeting that follows the device language (de / en / ar).
enum CustomerHomeGreeting {
    static func text(forName name: String) -> String {
        let hour = Calendar.current.component(.hour, from: Date())
        let trimmed = name.trimmingCharacters(in: .whitespacesAndNewlines)
        let lang = CustomerPortalLocale.languageCode

        switch lang {
        case "ar":
            return arabic(hour: hour, name: trimmed)
        case "de":
            return german(hour: hour, name: trimmed)
        default:
            return english(hour: hour, name: trimmed)
        }
    }

    private static func english(hour: Int, name: String) -> String {
        let salutation: String
        switch hour {
        case 5..<12: salutation = "Good morning"
        case 12..<17: salutation = "Good afternoon"
        case 17..<22: salutation = "Good evening"
        default: salutation = "Welcome back"
        }
        return name.isEmpty ? salutation : "\(salutation), \(name)"
    }

    private static func german(hour: Int, name: String) -> String {
        let salutation: String
        switch hour {
        case 5..<12: salutation = "Guten Morgen"
        case 12..<17: salutation = "Guten Tag"
        case 17..<22: salutation = "Guten Abend"
        default: salutation = "Willkommen zurück"
        }
        return name.isEmpty ? salutation : "\(salutation), \(name)"
    }

    private static func arabic(hour: Int, name: String) -> String {
        let salutation: String
        switch hour {
        case 5..<12: salutation = "صباح الخير"
        case 12..<17: salutation = "مساء الخير"
        case 17..<22: salutation = "مساء الخير"
        default: salutation = "مرحباً بعودتك"
        }
        return name.isEmpty ? salutation : "\(salutation)، \(name)"
    }
}
