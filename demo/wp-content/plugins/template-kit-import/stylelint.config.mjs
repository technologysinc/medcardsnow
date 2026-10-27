export default {
  extends: ['stylelint-config-standard-scss'],
  rules: {
    // Preserve existing CSS output — these auto-fix rules change rendered CSS
    'alpha-value-notation': null,
    'color-function-notation': null,
    'color-function-alias-notation': null,
    'media-feature-range-notation': null,

    // value-keyword-case: font-family names include vendor-prefixed / mixed-case names
    'value-keyword-case': null,

    // CSS Modules: camelCase class names are the project convention
    'selector-class-pattern': null,

    // CSS Modules: :global is a valid CSS Modules pseudo-class
    'selector-pseudo-class-no-unknown': null,

    // CSS Modules: camelCase keyframe names (e.g. iconSpin)
    'keyframes-name-pattern': null,

    // CSS Modules: @extend used with regular class selectors
    'scss/at-extend-no-missing-placeholder': null,

    // SCSS naming: codebase uses camelCase variables and mixins
    'scss/dollar-variable-pattern': null,
    'scss/at-mixin-pattern': null,

    // SCSS variable grouping uses blank lines between logical groups
    'scss/dollar-variable-empty-line-before': null,

    // Font-family: generic fallback not always required in component-scope font stacks
    'font-family-no-missing-generic-family-keyword': null,
    'font-family-name-quotes': null,

    // Vendor prefixes: legacy -webkit- prefixes in existing code
    'property-no-vendor-prefix': null,

    // Deprecated keyword: word-break: break-word is used in existing code
    'declaration-property-value-keyword-no-deprecated': null,

    // Nesting depth and specificity are intentional in the existing codebase
    'no-descending-specificity': null,

    // Keep existing property ordering — don't force shorthand rewrites
    'declaration-block-no-redundant-longhand-properties': null,

    // Preserve existing numeric precision in calc/flex expressions
    'number-max-precision': null,

    // Formatting: don't enforce empty lines (migration goal is security, not style)
    'rule-empty-line-before': null,
    'at-rule-empty-line-before': null,
    'declaration-empty-line-before': null
  }
}
