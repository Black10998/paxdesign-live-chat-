/**
 * Ensures master-admin customer editor does not show the logged-in admin's level
 * on a customer with no assigned PAXDesign level.
 */

function isExplicitCustomerProfile(profile, user) {
  if (!profile || typeof profile !== 'object') return false;
  if (profile.id && user && user.id && Number(profile.id) !== Number(user.id)) return true;
  return profile.has_customer_level !== undefined || profile.customer_level !== undefined;
}

function accountLevelData(profile, user, C, opts) {
  opts = opts || {};
  profile = profile || {};
  var strict = !!opts.strict || isExplicitCustomerProfile(profile, user);
  if (strict) {
    return {
      customer_level: Number(profile.customer_level) || 0,
      level_label: profile.level_label || '',
      level_title: profile.level_title || '',
      level_description: profile.level_description || '',
      has_customer_level: !!profile.has_customer_level,
    };
  }
  return {
    customer_level: profile.customer_level || user.customer_level || (C.customerLevel && C.customerLevel.customer_level) || 0,
    level_label: profile.level_label || user.level_label || (C.customerLevel && C.customerLevel.level_label) || '',
    level_title: profile.level_title || user.level_title || (C.customerLevel && C.customerLevel.level_title) || '',
    level_description: profile.level_description || user.level_description || (C.customerLevel && C.customerLevel.level_description) || '',
    has_customer_level: !!(profile.has_customer_level || user.has_customer_level || (C.customerLevel && C.customerLevel.has_customer_level)),
  };
}

function renderCustomerLevelBadge(profile, user, C, opts) {
  var level = accountLevelData(profile, user, C, opts);
  if (!level.has_customer_level || !level.level_label) return '';
  return '<span class="pdx-account-level-badge">' + level.level_label + '</span>';
}

const masterAdmin = {
  id: 1,
  customer_level: 1,
  level_label: 'PAXDesign Level 01 — Gold',
  has_customer_level: true,
};

const alkhaCustomer = {
  id: 482,
  display_name: 'ALKHA',
  email: 'gltcrtl.play@gmail.com',
  customer_level: 0,
  level_label: '',
  has_customer_level: false,
  avatar_preset: 'pax-vip-01',
};

const C = {
  customerLevel: {
    customer_level: 1,
    level_label: 'PAXDesign Level 01 — Gold',
    has_customer_level: true,
  },
};

const badgeForAlkha = renderCustomerLevelBadge(alkhaCustomer, masterAdmin, C);
if (badgeForAlkha !== '') {
  throw new Error('Expected no level badge for customer with no level, got: ' + badgeForAlkha);
}

const badgeForAdmin = renderCustomerLevelBadge(null, masterAdmin, C);
if (!badgeForAdmin.includes('PAXDesign Level 01')) {
  throw new Error('Expected master admin own level badge when profile is null');
}

const leveledCustomer = {
  id: 482,
  customer_level: 3,
  level_label: 'PAXDesign Level 03 — Diamond',
  has_customer_level: true,
};
const badgeForLeveled = renderCustomerLevelBadge(leveledCustomer, masterAdmin, C);
if (!badgeForLeveled.includes('Level 03')) {
  throw new Error('Expected customer level badge for assigned level');
}

console.log('OK: admin customer level badge consistency tests passed');
