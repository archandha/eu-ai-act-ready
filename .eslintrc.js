module.exports = {
    extends: [
        'plugin:@wordpress/eslint-plugin/recommended'
    ],
    parserOptions: {
        ecmaVersion: 2018,
        sourceType: 'module'
    },
    globals: {
        euaiactreadyAjax: 'readonly',
        ajaxurl: 'readonly',
        euaiactreadyRecheck: 'readonly',
        euaiactreadyChatbotTransparencyConfig: 'readonly',
        inlineEditPost: 'readonly',
        Formilla: 'readonly',
        Intercom: 'readonly',
        drift: 'readonly',
        tidioChatApi: 'readonly',
        Tawk_API: 'readonly',
        zE: 'readonly',
        LiveChatWidget: 'readonly',
        $crisp: 'readonly',
        fcWidget: 'readonly',
        jQuery: 'readonly',
        location: 'readonly',
        confirm: 'readonly',
        alert: 'readonly'
    },
    settings: {
        jsdoc: {
            preferredTypes: {
                jQuery: 'jQuery'
            }
        }
    },
    rules: {
        'no-alert': 'off',
        'no-undef': 'off',
        'camelcase': 'off',
        'no-shadow': 'off',
        'no-unused-expressions': 'off',
        '@wordpress/no-unused-vars-before-return': 'off',
        '@wordpress/no-global-active-element': 'off',
        'jsdoc/require-param-type': 'off',
        'jsdoc/require-returns-type': 'off',
    }
};
