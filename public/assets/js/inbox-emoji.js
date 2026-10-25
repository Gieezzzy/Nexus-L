/* Emoji set for the Inbox (same seven categories as the RMS Android app). */
window.NexusEmoji = (() => {
  const split = (s) => (typeof Intl !== 'undefined' && Intl.Segmenter
    ? [...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(s)].map((x) => x.segment).filter((x) => x.trim())
    : Array.from(s).filter((x) => x.trim()));
  const cats = [
    { title: 'Smileys & People', icon: '😀', list: split('😀😃😄😁😆😅🤣😂🙂🙃🫠😉😊😇🥰😍🤩😘😗😚😙😋😛😜🤪😝🤑🤗🫣🤭🫢🫡🤫🤔🤐🤨😐😑😶🫥😏😒🙄😬🤥😌😔😪🤤😴😷🤒🤕🤢🤮🤧🥵🥶🥴😵🤯🤠🥳🥸😎🤓🧐😕🫤😟🙁😮😯😲😳🥺🥹😦😧😨😰😥😢😭😱😖😣😞😓😩😫🥱😤😡😠🤬😈👿💀💩🤡👻👽👾🤖😺😸😹😻😼😽🙀😿😾👋🤚🖐✋🖖👌🤌🤏✌🤞🫰🤟🤘🤙👈👉👆👇☝🫵👍👎✊👊🤛🤜👏🙌🫶👐🤲🤝🙏✍💅🤳💪🧠👀👶🧒👦👧🧑👨👩🧓👴👵') },
    { title: 'Nature & Animals', icon: '🐶', list: split('🌱🌿☘🍀🪴🍂🍁🍄🌾💐🌷🌹🥀🌺🌸🌼🌻🌞🌝🌛🌜🌚🌕🌖🌗🌘🌑🌒🌓🌔🌙🌎🌍🌏💥💫💦💨🐵🐒🦍🐶🐕🐩🐺🦊🦝🐱🐈🦁🐯🐅🐆🐴🐎🦄🦓🦌🐮🐂🐃🐄🐷🐖🐗🐏🐑🐐🐪🐫🦙🦒🐘🦏🐭🐁🐀🐹🐰🐇🦔🦇🐻🐨🐼🦥🦦🦨🐔🐓🐣🐤🐥🐦🐧🦅🦆🦉🐸🐢🐍🐲🐳🐬🐟🐠🦈🐙🦋🐛🐝🐞') },
    { title: 'Food & Drink', icon: '🍔', list: split('🍇🍈🍉🍊🍋🍌🍍🥭🍎🍏🍐🍑🍒🍓🫐🥝🍅🥥🥑🍆🥔🥕🌽🌶🥒🥬🥦🧄🧅🥜🍞🥐🥖🥨🥯🥞🧇🧀🍖🍗🥩🥓🍔🍟🍕🌭🥪🌮🌯🥙🧆🥚🍳🥘🍲🥣🥗🍿🧈🧂🥫🍱🍘🍙🍚🍛🍜🍝🍠🍢🍣🍤🍥🥮🍡🥟🥠🥡🍦🍧🍨🍩🍪🎂🍰🧁🥧🍫🍬🍭🍮🍯🍼🥛☕🍵🍶🍾🍷🍸🍹🍺🍻🥂🥃🥤🧃🧉') },
    { title: 'Activities', icon: '⚽', list: split('⚽🏀🏈⚾🥎🎾🏐🏉🥏🎱🏓🏸🏒🏑🥍🏏🥅⛳🏹🎣🥊🥋🎽🛹🛼🛷⛸🥌🎿⛷🏂🏋🤼🤸⛹🤺🤾🏌🏇🧘🏄🏊🤽🚣🧗🚴🚵🎪🎭🎨🎬🎤🎧🎼🎹🥁🎷🎺🎸🎻🎲🎯🎳🎮🎰🧩') },
    { title: 'Travel & Places', icon: '🚗', list: split('🚗🚕🚙🚌🚎🏎🚓🚑🚒🚐🛻🚚🚛🚜🏍🛵🚲🛴🛺🚨🚔🚍🚘🚖🚡🚠🚟🚃🚋🚝🚄🚅🚆🚇🚈🚉✈🛩🛫🛬🚀🛸🚁⛵🚤🛥🚢⚓🚧⛽🚦🚥🗺🗿🗽🗼🏰🏯🏟🎡🎢🎠⛲⛱🏖🏝🏜🌋⛰🏔🗻🏕⛺🏠🏡🏢🏣🏥🏦🏨🏩🏪🏫🏬🏭') },
    { title: 'Objects', icon: '💡', list: split('💡📱📲💻⌨🖥🖨🖱🕹🔦🕯🧯💸💵💴💶💷🪙💰💳💎⚖🧰🔧🔨⚒🛠⛏🔩⚙🧱🔗⛓🧲🔫💣🔪🗡🛡🚬⚰🏺🔮📿🧿💈⚗🔭🔬🕳🩹🩺💊💉🩸🧬🦠🧫🧪🌡🧹🧺🧻🚽🚰🚿🛁🔑🗝🚪🛋🛏🧸🖼🛍🛒🎁🎈🎏🎀🎊🎉✉📧📨📩📦📪📫📮📝📁📂📅📆📈📉📊📋📌📍📎🖇📏📐✂🗃🗄🗑🔒🔓') },
    { title: 'Symbols & Flags', icon: '❤️', list: split('❤🧡💛💚💙💜🖤🤍🤎💔❣💕💞💓💗💖💘💝💟☮✝☪🕉☸✡🔯🕎☯☦🛐⛎♈♉♊♋♌♍♎♏♐♑♒♓🆔⚛✅❌❓❗‼⁉💯🔥✨⭐🌟💢💬💭🔔🔕🎵🎶➕➖➗✖♾💲💱™©®🔴🟠🟡🟢🔵🟣⚫⚪🟤🔺🔻🔶🔷🏁🚩🎌🏴🏳🇵🇭🇺🇸🇯🇵🇰🇷🇨🇳🇬🇧🇫🇷🇩🇪🇮🇹🇪🇸🇨🇦🇦🇺🇧🇷🇮🇳🇲🇽🇷🇺🇸🇬🇲🇾🇹🇭🇻🇳🇦🇪') },
  ];
  const all = cats.flatMap((c) => c.list);
  return { cats, all };
})();
