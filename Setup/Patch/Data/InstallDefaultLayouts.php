<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Setup\Patch\Data;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use OOCS\LynxPageBuilder\Model\Design;

/**
 * Installs the layout a store starts life with: the two zones every page shares, the home page
 */
class InstallDefaultLayouts implements DataPatchInterface
{
    /**
     * Rows hold a JSON node list: `component` as "Vendor_Module/Path", `visible` required (else dropped), no `id`
     */
    private const TABLE = 'lynx_pagebuilder_layout';

    /**
     * Module data setup, json
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param Json $json
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly Json $json,
    ) {
    }

    /**
     * Get Dependencies
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        // Every row here carries a design id, and that design is created there.
        return [CreateDefaultDesign::class];
    }

    /**
     * Get Aliases
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Apply
     *
     * @return self
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable(self::TABLE);

        $rows = [];
        // Into the design an install starts with
        foreach ($this->getLayouts() as $path => $nodes) {
            $rows[] = [
                'design_id' => Design::DEFAULT_ID,
                'path'      => $path,
                'value'     => $this->json->serialize($nodes),
            ];
        }

        // On duplicate, because (design_id, path) is unique and this install may already hold rows this ships
        $connection->insertOnDuplicate($table, $rows, ['value']);

        return $this;
    }

    /**
     * Path → its nodes
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getLayouts(): array
    {
        return [
            'header' => $this->headerZone(),
            'footer' => $this->footerZone(),
            // Backstage: the non-rendering components (trackers, scripts) every page carries, the checkout included
            'backstage' => [],
            'cms_index_index' => $this->homePage(),

            // The sign-in, registration and account pages are installed by OOCS_LynxCustomer's InstallCustomerLayouts
            'contact_index_index' => [
                $this->centeredPage([
                    ...$this->pageTitle('Contact Us', "Drop us a line and we'll get back to you as soon as possible."),
                    $this->node('default_contact_form', 'Components/ContactForm', 'Contact Form'),
                ]),
            ],
            'cms_noroute_index' => [
                $this->centeredPage([
                    $this->node('default_not_found', 'Components/NotFound', 'Not Found'),
                    ...$this->pageTitle(
                        'Page Not Found',
                        'The page you requested could not be found. It may have been moved or no longer exists.'
                    ),
                    $this->node('default_button', 'Components/Button', 'Button', [
                        'label'   => ['default' => 'Continue Shopping'],
                        'href'    => '/',
                        'variant' => 'primary',
                        'size'    => 'md',
                        'justify' => 'center',
                        'new_tab' => false,
                    ]),
                ]),
            ],
            // The product, category and search pages are installed by OOCS_LynxCatalog's InstallCatalogLayouts
            'checkout_cart_index' => [
                $this->group('sm', 'site', [
                    $this->node('default_checkout_cart', 'Components/CheckoutCart', 'Cart'),
                ]),
            ],
            'checkout_onepage_success' => [
                $this->group('lg', 'narrow', [
                    $this->node('default_checkout_success', 'Components/CheckoutSuccess', 'Checkout Success'),
                ]),
            ],
            'checkout_onepage_failure' => [
                $this->group('lg', 'narrow', [
                    $this->node('default_checkout_failure', 'Components/CheckoutFailure', 'Checkout Failure'),
                ]),
            ],
        ];
    }

    /**
     * The three zones an admin can already build in the editor are shipped as the layout they were built as
     *
     * @return array<int, array<string, mixed>>
     */
    private function headerZone(): array
    {
        return $this->json->unserialize(<<<'JSON'
        [
          {
            "component": "OOCS_LynxThemeComponent/Components/Header",
            "name": "default_header",
            "label": "Header",
            "props": {},
            "visible": true,
            "slots": {
              "actions": [
                {
                  "component": "OOCS_LynxThemeComponent/Components/HeaderSearch",
                  "name": "default_header_search",
                  "label": "Header Search",
                  "props": {},
                  "visible": true
                },
                {
                  "component": "OOCS_LynxThemeComponent/Components/HeaderAccount",
                  "name": "default_header_account",
                  "label": "Header Account",
                  "props": {},
                  "visible": true
                },
                {
                  "component": "OOCS_LynxThemeComponent/Components/MiniCart",
                  "name": "default_mini_cart",
                  "label": "Mini Cart",
                  "props": {},
                  "visible": true
                }
              ]
            }
          }
        ]
        JSON);
    }

    /**
     * Footer Zone
     *
     * @return array<int, array<string, mixed>>
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function footerZone(): array
    {
        // Composed of primitives, so every column, link and line is the admin's
        return $this->json->unserialize(<<<'JSON'
        [
          {
            "component": "OOCS_LynxThemeComponent/Components/Group",
            "name": "default_group",
            "label": "Group",
            "custom_name": "Footer",
            "props": {
              "layout": "constrained",
              "width": "site",
              "padding_y": "md",
              "border": "top"
            },
            "visible": true,
            "slots": {
              "default": [
                {
                  "component": "OOCS_LynxThemeComponent/Components/Row",
                  "name": "default_row",
                  "label": "Row",
                  "props": {
                    "columns": 5,
                    "columns_tablet": 1,
                    "columns_mobile": 1,
                    "gap": 32
                  },
                  "visible": true,
                  "slots": {
                    "default": [
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Column",
                        "name": "default_column",
                        "label": "Column",
                        "custom_name": "Links",
                        "props": {
                          "span": 3
                        },
                        "visible": true,
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Row",
                              "name": "default_row",
                              "label": "Row",
                              "props": {
                                "columns": 3,
                                "columns_tablet": 3,
                                "columns_mobile": 1,
                                "gap": 32
                              },
                              "visible": true,
                              "slots": {
                                "default": [
                                  {
                                    "component": "OOCS_LynxThemeComponent/Components/LinkList",
                                    "name": "default_link_list",
                                    "label": "Link List",
                                    "custom_name": "Footer Links",
                                    "visible": true,
                                    "props": {
                                      "title": {
                                        "default": "Quick Links"
                                      },
                                      "links": [
                                        {
                                          "label": {
                                            "default": "Search Terms"
                                          },
                                          "url": "search/term/popular/"
                                        },
                                        {
                                          "label": {
                                            "default": "Privacy and Cookie Policy"
                                          },
                                          "url": "privacy-policy-cookie-restriction-mode"
                                        },
                                        {
                                          "label": {
                                            "default": "Advanced Search"
                                          },
                                          "url": "catalogsearch/advanced/"
                                        },
                                        {
                                          "label": {
                                            "default": "Orders and Returns"
                                          },
                                          "url": "sales/guest/form/"
                                        },
                                        {
                                          "label": {
                                            "default": "Contact Us"
                                          },
                                          "url": "contact/"
                                        }
                                      ]
                                    }
                                  },
                                  {
                                    "component": "OOCS_LynxThemeComponent/Components/StoreSwitcher",
                                    "name": "default_store_switcher",
                                    "label": "Store Switcher",
                                    "visible": true,
                                    "props": {}
                                  },
                                  {
                                    "component": "OOCS_LynxThemeComponent/Components/CurrencySwitcher",
                                    "name": "default_currency_switcher",
                                    "label": "Currency Switcher",
                                    "visible": true,
                                    "props": {}
                                  }
                                ]
                              }
                            }
                          ]
                        }
                      },
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Column",
                        "name": "default_column",
                        "label": "Column",
                        "custom_name": "Newsletter",
                        "props": {
                          "span": 2
                        },
                        "visible": true,
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/NewsletterForm",
                              "name": "default_newsletter_form",
                              "label": "Newsletter Form",
                              "visible": true,
                              "props": {
                                "title": {
                                  "default": "Newsletter"
                                },
                                "placeholder": {
                                  "default": "Enter your email"
                                }
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Spacer",
                              "name": "default_spacer",
                              "label": "Spacer",
                              "visible": true,
                              "props": {
                                "height": 24
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/SocialLinks",
                              "name": "default_social_links",
                              "label": "Social Links",
                              "visible": true,
                              "props": {
                                "title": {
                                  "default": "Follow us"
                                },
                                "items": [
                                  {
                                    "network": "instagram",
                                    "url": "https://www.instagram.com/"
                                  },
                                  {
                                    "network": "facebook",
                                    "url": "https://www.facebook.com/"
                                  },
                                  {
                                    "network": "x",
                                    "url": "https://x.com/"
                                  },
                                  {
                                    "network": "youtube",
                                    "url": "https://www.youtube.com/"
                                  }
                                ]
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              ]
            }
          },
          {
            "component": "OOCS_LynxThemeComponent/Components/Group",
            "name": "default_group",
            "label": "Group",
            "custom_name": "Copyright",
            "props": {
              "layout": "constrained",
              "width": "site",
              "padding_y": "sm",
              "border": "top"
            },
            "visible": true,
            "slots": {
              "default": [
                {
                  "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                  "name": "default_paragraph",
                  "label": "Paragraph",
                  "custom_name": "Copyright",
                  "visible": true,
                  "props": {
                    "content": {
                      "default": "<p>© 2026 Lynx. All rights reserved.</p>"
                    },
                    "size": "sm"
                  }
                }
              ]
            }
          }
        ]
        JSON);
    }

    // phpcs:disable Generic.Files.LineLength -- JSON strings of the shipped home page cannot be wrapped
    /**
     * Home Page
     *
     * @return array<int, array<string, mixed>>
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function homePage(): array
    {
        return $this->json->unserialize(<<<'JSON'
        [
          {
            "component": "OOCS_LynxThemeComponent/Components/Spacer",
            "name": "default_spacer",
            "label": "Spacer",
            "visible": true,
            "props": {
              "height": 24
            }
          },
          {
            "component": "OOCS_LynxThemeComponent/Components/Group",
            "name": "default_group",
            "label": "Group",
            "custom_name": "Hero",
            "visible": true,
            "props": {
              "align": "wide",
              "layout": "constrained",
              "width": "narrow",
              "padding_y": "lg",
              "gap": "md",
              "min_height": 480,
              "content_position": "center",
              "gradient": "soft",
              "rounded": "md",
              "tag": "section"
            },
            "slots": {
              "default": [
                {
                  "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                  "name": "default_paragraph",
                  "label": "Paragraph",
                  "visible": true,
                  "props": {
                    "content": {
                      "default": "<p><strong>Built with Lynx ✦</strong></p>"
                    },
                    "text_align": "center",
                    "size": "lg",
                    "color": "var(--color-primary)"
                  }
                },
                {
                  "component": "OOCS_LynxThemeComponent/Components/Heading",
                  "name": "default_heading",
                  "label": "Heading",
                  "visible": true,
                  "props": {
                    "text": {
                      "default": "Your new storefront is ready."
                    },
                    "level": "h1",
                    "text_align": "center",
                    "color": ""
                  }
                },
                {
                  "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                  "name": "default_paragraph",
                  "label": "Paragraph",
                  "visible": true,
                  "props": {
                    "content": {
                      "default": "<p>You're looking at the default homepage. Change any part of it in PageBuilder.</p>"
                    },
                    "text_align": "center",
                    "size": "lg",
                    "color": ""
                  }
                },
                {
                  "component": "OOCS_LynxThemeComponent/Components/Button",
                  "name": "default_button",
                  "label": "Button",
                  "visible": true,
                  "props": {
                    "label": {
                      "default": "Explore Lynx"
                    },
                    "href": "https://docs.oocscommerce.com",
                    "variant": "primary",
                    "size": "lg",
                    "justify": "center",
                    "new_tab": true
                  }
                }
              ]
            }
          },
          {
            "component": "OOCS_LynxThemeComponent/Components/Group",
            "name": "default_group",
            "label": "Group",
            "custom_name": "Features",
            "visible": true,
            "props": {
              "align": "wide",
              "layout": "full",
              "padding_y": "sm",
              "padding_x": "none",
              "tag": "section"
            },
            "slots": {
              "default": [
                {
                  "component": "OOCS_LynxThemeComponent/Components/Row",
                  "name": "default_row",
                  "label": "Row",
                  "visible": true,
                  "props": {
                    "columns": 3,
                    "columns_tablet": 3,
                    "columns_mobile": 1,
                    "gap": 16,
                    "vertical_align": "stretch"
                  },
                  "slots": {
                    "default": [
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Group",
                        "name": "default_group",
                        "label": "Group",
                        "custom_name": "Feature — Instant navigation",
                        "visible": true,
                        "props": {
                          "layout": "full",
                          "padding_y": "md",
                          "padding_x": "default",
                          "gap": "sm",
                          "border": "all",
                          "rounded": "md",
                          "tag": "div"
                        },
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "custom_name": "Title",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<h2 class=\"flex items-center gap-3 text-2xl font-semibold\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.75\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"shrink-0 text-primary\" aria-hidden=\"true\"><path d=\"M13 2.5 5 13.5h6l-1 8 8-11h-6l1-8z\"/></svg>Instant navigation</h2>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<p>Pages switch in place without a full reload, so shoppers never wait on a blank screen.</p>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            }
                          ]
                        }
                      },
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Group",
                        "name": "default_group",
                        "label": "Group",
                        "custom_name": "Feature — Visual page builder",
                        "visible": true,
                        "props": {
                          "layout": "full",
                          "padding_y": "md",
                          "padding_x": "default",
                          "gap": "sm",
                          "border": "all",
                          "rounded": "md",
                          "tag": "div"
                        },
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "custom_name": "Title",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<h2 class=\"flex items-center gap-3 text-2xl font-semibold\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.75\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"shrink-0 text-primary\" aria-hidden=\"true\"><path d=\"M3.5 20.5V17l9-9 3.5 3.5-9 9H3.5z\"/><path d=\"m10.5 10 3.5 3.5\"/><path d=\"M19 2.5l.85 2.15L22 5.5l-2.15.85L19 8.5l-.85-2.15L16 5.5l2.15-.85L19 2.5z\"/></svg>Visual page builder</h2>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<p>Drag, drop and restyle any block on this page, or let AI build it for you, then share a preview link before you publish.</p>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            }
                          ]
                        }
                      },
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Group",
                        "name": "default_group",
                        "label": "Group",
                        "custom_name": "Feature — 80+ ready components",
                        "visible": true,
                        "props": {
                          "layout": "full",
                          "padding_y": "md",
                          "padding_x": "default",
                          "gap": "sm",
                          "border": "all",
                          "rounded": "md",
                          "tag": "div"
                        },
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "custom_name": "Title",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<h2 class=\"flex items-center gap-3 text-2xl font-semibold\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.75\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"shrink-0 text-primary\" aria-hidden=\"true\"><rect x=\"3.5\" y=\"3.5\" width=\"7\" height=\"7\" rx=\"1.5\"/><rect x=\"13.5\" y=\"3.5\" width=\"7\" height=\"7\" rx=\"1.5\"/><rect x=\"3.5\" y=\"13.5\" width=\"7\" height=\"7\" rx=\"1.5\"/><rect x=\"13.5\" y=\"13.5\" width=\"7\" height=\"7\" rx=\"1.5\"/></svg>80+ ready components</h2>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<p>Banners, carousels, product lists, account pages and more, ready to drop in.</p>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              ]
            }
          },
          {
            "component": "OOCS_LynxThemeComponent/Components/Group",
            "name": "default_group",
            "label": "Group",
            "custom_name": "New arrivals",
            "visible": true,
            "props": {
              "align": "wide",
              "layout": "full",
              "padding_y": "sm",
              "padding_x": "none",
              "anchor": "new-arrivals",
              "tag": "section"
            },
            "slots": {
              "default": [
                {
                  "component": "OOCS_LynxCatalog/Components/ProductCarousel",
                  "name": "default_product_carousel",
                  "label": "Product Carousel",
                  "visible": true,
                  "props": {
                    "title": {
                      "default": "New arrivals"
                    },
                    "source": "recent",
                    "skus": "",
                    "limit": 8,
                    "columns": 4,
                    "show_arrows": true
                  }
                }
              ]
            }
          },
          {
            "component": "OOCS_LynxThemeComponent/Components/Group",
            "name": "default_group",
            "label": "Group",
            "custom_name": "Promotions",
            "visible": true,
            "props": {
              "align": "wide",
              "layout": "full",
              "padding_y": "sm",
              "padding_x": "none",
              "tag": "section"
            },
            "slots": {
              "default": [
                {
                  "component": "OOCS_LynxThemeComponent/Components/Row",
                  "name": "default_row",
                  "label": "Row",
                  "visible": true,
                  "props": {
                    "columns": 3,
                    "columns_tablet": 3,
                    "columns_mobile": 1,
                    "gap": 16,
                    "vertical_align": "stretch"
                  },
                  "slots": {
                    "default": [
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Group",
                        "name": "default_group",
                        "label": "Group",
                        "custom_name": "Promo — New season edit",
                        "visible": true,
                        "props": {
                          "layout": "full",
                          "padding_y": "md",
                          "padding_x": "default",
                          "gap": "sm",
                          "min_height": 280,
                          "content_position": "bottom",
                          "rounded": "md",
                          "tag": "div",
                          "gradient": "soft"
                        },
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Heading",
                              "name": "default_heading",
                              "label": "Heading",
                              "visible": true,
                              "props": {
                                "text": {
                                  "default": "New season edit"
                                },
                                "level": "h2",
                                "text_align": "left",
                                "color": ""
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<p>Fresh picks, just landed.</p>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Button",
                              "name": "default_button",
                              "label": "Button",
                              "visible": true,
                              "props": {
                                "label": {
                                  "default": "Shop now"
                                },
                                "href": "#",
                                "variant": "primary",
                                "size": "md",
                                "justify": "start",
                                "new_tab": false
                              }
                            }
                          ]
                        }
                      },
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Group",
                        "name": "default_group",
                        "label": "Group",
                        "custom_name": "Promo — Gift ideas",
                        "visible": true,
                        "props": {
                          "layout": "full",
                          "padding_y": "md",
                          "padding_x": "default",
                          "gap": "sm",
                          "min_height": 280,
                          "content_position": "bottom",
                          "rounded": "md",
                          "tag": "div",
                          "gradient": "bold",
                          "color": "var(--color-primary-content)"
                        },
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Heading",
                              "name": "default_heading",
                              "label": "Heading",
                              "visible": true,
                              "props": {
                                "text": {
                                  "default": "Gift ideas"
                                },
                                "level": "h2",
                                "text_align": "left",
                                "color": "var(--color-primary-content)"
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<p>Something for everyone on your list.</p>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": "var(--color-primary-content)"
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Button",
                              "name": "default_button",
                              "label": "Button",
                              "visible": true,
                              "props": {
                                "label": {
                                  "default": "Find a gift"
                                },
                                "href": "#",
                                "variant": "light",
                                "size": "md",
                                "justify": "start",
                                "new_tab": false
                              }
                            }
                          ]
                        }
                      },
                      {
                        "component": "OOCS_LynxThemeComponent/Components/Group",
                        "name": "default_group",
                        "label": "Group",
                        "custom_name": "Promo — Members save more",
                        "visible": true,
                        "props": {
                          "layout": "full",
                          "padding_y": "md",
                          "padding_x": "default",
                          "gap": "sm",
                          "min_height": 280,
                          "content_position": "bottom",
                          "rounded": "md",
                          "tag": "div",
                          "background": "var(--color-base-200)"
                        },
                        "slots": {
                          "default": [
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Heading",
                              "name": "default_heading",
                              "label": "Heading",
                              "visible": true,
                              "props": {
                                "text": {
                                  "default": "Members save more"
                                },
                                "level": "h2",
                                "text_align": "left",
                                "color": ""
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Paragraph",
                              "name": "default_paragraph",
                              "label": "Paragraph",
                              "visible": true,
                              "props": {
                                "content": {
                                  "default": "<p>Create an account for early access and exclusive offers.</p>"
                                },
                                "text_align": "left",
                                "size": "base",
                                "color": ""
                              }
                            },
                            {
                              "component": "OOCS_LynxThemeComponent/Components/Button",
                              "name": "default_button",
                              "label": "Button",
                              "visible": true,
                              "props": {
                                "label": {
                                  "default": "Sign up"
                                },
                                "href": "customer/account/create",
                                "variant": "primary",
                                "size": "md",
                                "justify": "start",
                                "new_tab": false
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              ]
            }
          },
          {
            "component": "OOCS_LynxThemeComponent/Components/Group",
            "name": "default_group",
            "label": "Group",
            "custom_name": "Popular products",
            "visible": true,
            "props": {
              "align": "wide",
              "layout": "full",
              "padding_y": "sm",
              "padding_x": "none",
              "tag": "section"
            },
            "slots": {
              "default": [
                {
                  "component": "OOCS_LynxCatalog/Components/ProductCarousel",
                  "name": "default_product_carousel",
                  "label": "Product Carousel",
                  "visible": true,
                  "props": {
                    "title": {
                      "default": "Popular products"
                    },
                    "source": "bestsellers",
                    "period": "year",
                    "limit": 8,
                    "columns": 4,
                    "show_arrows": true
                  }
                }
              ]
            }
          }
        ]
        JSON);
    }
    // phpcs:enable Generic.Files.LineLength

    /**
     * A page whose blocks sit centred under one another
     *
     * @param array<int,array<string,mixed>> $children
     * @return array<string, mixed>
     */
    private function centeredPage(array $children): array
    {
        return $this->node('default_group', 'Components/Group', 'Group', [
            'layout'    => 'constrained',
            'width'     => 'site',
            'padding_y' => 'sm',
            'padding_x' => 'none',
            'gap'       => 'md',
        ], $children);
    }

    /**
     * The page's centred H1, and its intro under it when there is one
     *
     * @param string $title
     * @param string $intro
     * @return array<int, array<string, mixed>>
     */
    private function pageTitle(string $title, string $intro = ''): array
    {
        $nodes = [$this->node('default_heading', 'Components/Heading', 'Heading', [
            'text'       => ['default' => $title],
            'level'      => 'h1',
            'text_align' => 'center',
            'color'      => '',
        ])];
        if ($intro !== '') {
            $nodes[] = $this->node('default_paragraph', 'Components/Paragraph', 'Paragraph', [
                'content'    => ['default' => '<p>' . $intro . '</p>'],
                'text_align' => 'center',
                'size'       => 'sm',
                'color'      => '',
            ]);
        }

        return $nodes;
    }

    /**
     * The frame a page's content sits in: content components carry no width or padding of their own
     *
     * @param string $paddingY
     * @param string $width
     * @param array $children
     * @return array<string, mixed>
     */
    private function group(string $paddingY, string $width, array $children): array
    {
        return $this->node('default_group', 'Components/Group', 'Group', [
            'layout'    => $width === 'full' ? 'full' : 'constrained',
            'width'     => $width === 'full' ? 'site' : $width,
            'padding_y' => $paddingY,
            // Inside the page frame (Layouts/Default), whose gutters are already there.
            'padding_x' => 'none',
        ], $children);
    }

    /**
     * Node
     *
     * @param string $name
     * @param string $component
     * @param string $label
     * @param array $props
     * @param array $children
     * @param string $slot which declared slot the children fill
     * @return array<string, mixed>
     */
    private function node(
        string $name,
        string $component,
        string $label,
        array $props = [],
        array $children = [],
        string $slot = 'default'
    ): array {
        $node = [
            'label'     => $label,
            'name'      => $name,
            'component' => 'OOCS_LynxThemeComponent/' . $component,
            // Left as an array when empty, which encodes to []
            'props'     => $props,
            'visible'   => true,
        ];

        if ($children) {
            // Keyed by slot name: a component may declare more than one slot
            $node['slots'] = [$slot => $children];
        }

        return $node;
    }
}
