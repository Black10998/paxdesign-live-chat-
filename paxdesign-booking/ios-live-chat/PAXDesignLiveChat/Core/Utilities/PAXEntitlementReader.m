#import "PAXEntitlementReader.h"
#import <Security/Security.h>

NSString * _Nullable PAXCopyEntitlement(NSString *key) {
    if (key.length == 0) {
        return nil;
    }

    SecTaskRef task = SecTaskCreateFromSelf(NULL);
    if (task == NULL) {
        return nil;
    }

    CFTypeRef value = SecTaskCopyValueForEntitlement(task, (__bridge CFStringRef)key);
    if (value == NULL) {
        return nil;
    }

    if ([(__bridge id)value isKindOfClass:[NSString class]]) {
        return (__bridge_transfer NSString *)value;
    }

    CFRelease(value);
    return nil;
}
