import "gatsby-theme-apollo-docs/src/prism.less"
import "prismjs/plugins/line-numbers/prism-line-numbers.css"
import DocsetSwitcher from "gatsby-theme-apollo-docs/src/components/docset-switcher"
import Header from "gatsby-theme-apollo-docs/src/components/header"
import HeaderButton from "gatsby-theme-apollo-docs/src/components/header-button"
import PropTypes from "prop-types"
import React, { createContext, useMemo, useRef, useState } from "react"
import Search from "gatsby-theme-apollo-docs/src/components/search"
import styled from "@emotion/styled"
import useLocalStorage from "react-use/lib/useLocalStorage"
import { Button } from "@apollo/space-kit/Button"
import {
	FlexWrapper,
	Layout,
	MenuButton,
	Sidebar,
	SidebarNav,
	breakpoints,
	colors,
	useResponsiveSidebar,
} from "gatsby-theme-apollo-core"
import { Helmet } from "react-helmet"
import { IconLayoutModule } from "@apollo/space-kit/icons/IconLayoutModule"
import { Link, graphql, navigate, useStaticQuery } from "gatsby"
// import { MobileLogo } from "gatsby-theme-apollo-docs/src/components/mobile-logo"
import { Select } from "gatsby-theme-apollo-docs/src/components/select"
import { SelectedLanguageContext } from "gatsby-theme-apollo-docs/src/components/multi-code-block"
import {
	getSpectrumUrl,
	getVersionBasePath,
} from "gatsby-theme-apollo-docs/src/utils"
import { size } from "polished"

import "../../components/style.css"

const Main = styled.main({
	flexGrow: 1,
})

const ButtonWrapper = styled.div({
	flexGrow: 1,
})

const StyledButton = styled(Button)({
	width: `100%`,
	":not(:hover)": {
		backgroundColor: colors.background,
	},
})

const StyledIcon = styled(IconLayoutModule)(size(16), {
	marginLeft: `auto`,
})

const MobileNav = styled.div({
	display: `none`,
	[breakpoints.md]: {
		display: `flex`,
		alignItems: `center`,
		marginRight: 32,
		color: colors.text1,
	},
})

const HeaderInner = styled.span({
	display: `flex`,
	alignItems: `center`,
	justifyContent: `space-between`,
	marginBottom: 32,
})

const Eyebrow = styled.div({
	flexShrink: 0,
	padding: `8px 56px`,
	backgroundColor: colors.background,
	color: colors.primary,
	fontSize: 14,
	position: `sticky`,
	top: 0,
	a: {
		color: `inherit`,
		fontWeight: 600,
	},
	[breakpoints.md]: {
		padding: `8px 24px`,
	},
})

const NetlifyLink = styled.a({
	marginRight: 12,
})

function getVersionLabel(version) {
	return `v${version}`
}

const GA_EVENT_CATEGORY_SIDEBAR = `Sidebar`

function handleToggleAll(expanded) {
	if (typeof window.analytics !== `undefined`) {
		window.analytics.track(`Toggle all`, {
			category: GA_EVENT_CATEGORY_SIDEBAR,
			label: expanded ? `expand` : `collapse`,
		})
	}
}

function handleToggleCategory(title, expanded) {
	if (typeof window.analytics !== `undefined`) {
		window.analytics.track(`Toggle category`, {
			category: GA_EVENT_CATEGORY_SIDEBAR,
			label: title,
			value: Number(expanded),
		})
	}
}

export const NavItemsContext = createContext()

export default function PageLayout(props) {
	const data = useStaticQuery(
		graphql`
			{
				site {
					siteMetadata {
						title
						siteName
					}
				}
			}
		`
	)

	const {
		sidebarRef,
		openSidebar,
		sidebarOpen,
		handleWrapperClick,
		handleSidebarNavLinkClick,
	} = useResponsiveSidebar()

	const buttonRef = useRef(null)
	const [menuOpen, setMenuOpen] = useState(false)
	const selectedLanguageState = useLocalStorage(`docs-lang`)

	function openMenu() {
		setMenuOpen(true)
	}

	function closeMenu() {
		setMenuOpen(false)
	}

	const { pathname } = props.location
	const { siteName, title } = data.site.siteMetadata
	const {
		subtitle,
		sidebarContents,
		versions,
		versionDifference,
		versionBasePath,
		defaultVersion,
	} = props.pageContext
	const {
		spectrumHandle,
		twitterHandle,
		youtubeUrl,
		navConfig = {},
		footerNavConfig,
		logoLink,
		algoliaApiKey,
		algoliaIndexName,
		menuTitle,
	} = props.pluginOptions

	const navItems = useMemo(
		() =>
			Object.entries(navConfig).map(([title, navItem]) => ({
				...navItem,
				title,
			})),
		[navConfig]
	)

	const hasNavItems = navItems.length > 0
	const sidebarTitle = (
		<span className="title-sidebar">{subtitle || siteName}</span>
	)

	return (
		<Layout>
			<Helmet titleTemplate={[`%s`, title].filter(Boolean).join(` - `)}>
				<link
					rel="stylesheet"
					href="https://cdn.jsdelivr.net/npm/docsearch.js@2/dist/cdn/docsearch.min.css"
				/>
				<meta
					name="viewport"
					content="width=device-width, initial-scale=1, maximum-scale=1"
				/>
			</Helmet>
			<FlexWrapper onClick={handleWrapperClick}>
				<Sidebar
					responsive
					className="sidebar"
					open={sidebarOpen}
					ref={sidebarRef}
					title={siteName}
					logoLink={logoLink}
				>
					<HeaderInner>
						{hasNavItems ? (
							<ButtonWrapper ref={buttonRef}>
								<StyledButton
									feel="flat"
									color={colors.primary}
									size="small"
									onClick={openMenu}
									style={{ display: `block` }}
								>
									{sidebarTitle}
									<StyledIcon />
								</StyledButton>
							</ButtonWrapper>
						) : (
							sidebarTitle
						)}
						{versions && versions.length > 0 && (
							<Select
								feel="flat"
								size="small"
								value={versionDifference ? versionBasePath : `/`}
								onChange={navigate}
								style={{ marginLeft: 8 }}
								options={versions.reduce(
									(acc, version) => {
										return {
											...acc,
											[getVersionBasePath(version)]: getVersionLabel(version),
										}
									},
									{
										"/": defaultVersion
											? getVersionLabel(defaultVersion)
											: `Latest`,
									}
								)}
							/>
						)}
					</HeaderInner>

					{sidebarContents && (
						<SidebarNav
							contents={sidebarContents}
							pathname={pathname}
							onToggleAll={handleToggleAll}
							onToggleCategory={handleToggleCategory}
							onLinkClick={handleSidebarNavLinkClick}
						/>
					)}
				</Sidebar>
				<Main>
					<Header
						beforeContent={
							<>
								{versionDifference !== 0 && (
									<Eyebrow>
										You&apos;re viewing documentation for a{` `}
										{versionDifference > 0
											? `version of this software that is in development`
											: `previous version of this software`}
										. <Link to="/">Switch to the latest stable version</Link>
									</Eyebrow>
								)}
							</>
						}
					>
						<MobileNav>
							<MenuButton onClick={openSidebar} />
							{/* <MobileLogo width={32} fill="currentColor" /> */}
						</MobileNav>
						{/* <NetlifyLink href="https://www.netlify.com">
              <img src="https://www.netlify.com/img/global/badges/netlify-color-bg.svg" alt="Deploys by Netlify" />
            </NetlifyLink> */}
						{algoliaApiKey && algoliaIndexName && (
							<Search
								siteName={siteName}
								apiKey={algoliaApiKey}
								indexName={algoliaIndexName}
							/>
						)}
						<HeaderButton />
					</Header>
					<SelectedLanguageContext.Provider value={selectedLanguageState}>
						<NavItemsContext.Provider value={navItems}>
							{props.children}
						</NavItemsContext.Provider>
					</SelectedLanguageContext.Provider>
				</Main>
			</FlexWrapper>
			{hasNavItems && (
				<DocsetSwitcher
					siteName={menuTitle || siteName}
					spectrumUrl={spectrumHandle && getSpectrumUrl(spectrumHandle)}
					twitterUrl={twitterHandle && `https://twitter.com/${twitterHandle}`}
					youtubeUrl={youtubeUrl}
					navItems={navItems}
					footerNavConfig={footerNavConfig}
					open={menuOpen}
					buttonRef={buttonRef}
					onClose={closeMenu}
				/>
			)}
		</Layout>
	)
}

PageLayout.propTypes = {
	children: PropTypes.node.isRequired,
	location: PropTypes.object.isRequired,
	pageContext: PropTypes.object.isRequired,
	pluginOptions: PropTypes.object.isRequired,
}
